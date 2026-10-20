<?php

namespace App\Support;

use App\Models\WebtopContainer;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Recoge metricas del host y disponibilidad de los contenedores Webtop.
 *
 * Nota: las metricas de CPU/RAM corresponden al servidor que ejecuta PHP, no a
 * cada contenedor de usuario. El sondeo de contenedores se hace en paralelo
 * para no encadenar los timeouts (antes: hasta 3s * 7 contenedores en serie).
 */
class SystemStatusMonitor
{
    private const WINDOWS = 'Windows';

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $timeout = max(1, (int) config('virthub.status.probe_timeout_seconds', 3));

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'timestamp_utc' => gmdate('c'),
            'cpu_usage_percent' => $this->cpuUsagePercent(),
            'ram_used_mb' => $this->ramUsedMb(),
            'ram_used_percent' => $this->ramUsedPercent(),
            'disk_used_percent' => $this->diskUsedPercent(),
            'webtop_online' => $this->webtopOnline($timeout),
            'container_status' => $this->containerStatuses($timeout),
        ];
    }

    private function isWindows(): bool
    {
        return stripos(PHP_OS_FAMILY, self::WINDOWS) !== false;
    }

    private function cpuUsagePercent(): ?float
    {
        if ($this->isWindows()) {
            $raw = $this->runCommand(
                'powershell -NoProfile -Command "(Get-CimInstance Win32_Processor | Measure-Object LoadPercentage -Average).Average" 2>$null'
            );

            return is_numeric($raw) ? round(min(100, max(0, (float) $raw)), 1) : null;
        }

        if (! function_exists('sys_getloadavg')) {
            return null;
        }

        $load = sys_getloadavg();

        if (! isset($load[0])) {
            return null;
        }

        $cores = (int) $this->runCommand('nproc 2>/dev/null');

        if ($cores <= 0) {
            $cores = 1;
        }

        return round(min(100, max(0, (((float) $load[0]) / $cores) * 100)), 1);
    }

    /**
     * @return array{total_kb: int|null, available_kb: int|null}
     */
    private function memory(): array
    {
        if ($this->isWindows()) {
            $total = $this->runCommand(
                'powershell -NoProfile -Command "(Get-CimInstance Win32_OperatingSystem).TotalVisibleMemorySize" 2>$null'
            );
            $free = $this->runCommand(
                'powershell -NoProfile -Command "(Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory" 2>$null'
            );

            return [
                'total_kb' => is_numeric($total) ? (int) $total : null,
                'available_kb' => is_numeric($free) ? (int) $free : null,
            ];
        }

        $raw = @file_get_contents('/proc/meminfo');

        if (! is_string($raw) || $raw === '') {
            return ['total_kb' => null, 'available_kb' => null];
        }

        return [
            'total_kb' => preg_match('/^MemTotal:\s+(\d+)\s+kB/im', $raw, $m) ? (int) $m[1] : null,
            'available_kb' => preg_match('/^MemAvailable:\s+(\d+)\s+kB/im', $raw, $m) ? (int) $m[1] : null,
        ];
    }

    private function ramUsedMb(): ?float
    {
        $usedKb = $this->usedKb();

        return $usedKb === null ? null : round($usedKb / 1024, 1);
    }

    private function ramUsedPercent(): ?float
    {
        $memory = $this->memory();
        $usedKb = $this->usedKb();

        if ($usedKb === null || $memory['total_kb'] === null || $memory['total_kb'] <= 0) {
            return null;
        }

        return round(($usedKb / $memory['total_kb']) * 100, 1);
    }

    private function usedKb(): ?int
    {
        $memory = $this->memory();

        if ($memory['total_kb'] === null || $memory['available_kb'] === null) {
            return null;
        }

        return max(0, $memory['total_kb'] - $memory['available_kb']);
    }

    private function diskUsedPercent(): ?float
    {
        $total = @disk_total_space(DIRECTORY_SEPARATOR);
        $free = @disk_free_space(DIRECTORY_SEPARATOR);

        if (! is_float($total) && ! is_int($total)) {
            return null;
        }

        if ($total <= 0 || (! is_float($free) && ! is_int($free))) {
            return null;
        }

        return round((($total - $free) / $total) * 100, 1);
    }

    private function webtopOnline(int $timeout): bool
    {
        $url = (string) config('virthub.containers.webtop_url', '');

        if ($url === '') {
            return false;
        }

        try {
            return Http::timeout($timeout)->get($url)->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Estado de cada escritorio registrado.
     *
     * La lista sale de la BASE DE DATOS, no de una lista fija: cuando se anade un
     * escritorio y se registra con `virthub:sync-containers`, el panel lo muestra
     * solo. Antes habia siete indices escritos en el codigo y el panel no podia
     * crecer ni encogerse.
     *
     * El sondeo se hace por la red interna de Docker (http://ct0:3000), no por la
     * URL publica, por dos motivos:
     *
     *   1. La URL publica pasa por Caddy, que responde 403 sin sesion. Un
     *      escritorio perfectamente sano aparecia como caido.
     *   2. No depende de que el tunel este levantado para saber si el contenedor
     *      responde.
     *
     * Un escritorio esta VIVO si contesta, aunque sea con 401 o 403: eso significa
     * que esta en pie y rechazando la peticion por falta de credenciales. Solo se
     * considera caido si no hay respuesta o el servidor falla con un error.
     *
     * @return array<string, array{name: string, url: string, online: bool, capacity: int|null}>
     */
    private function containerStatuses(int $timeout): array
    {
        $containers = $this->registeredContainers();

        if ($containers === []) {
            return [];
        }

        // Los escritorios de otra maquina necesitan mas margen que los locales.
        //
        // Los locales viven en la red de Docker y contestan en milisegundos; los
        // remotos cruzan la red local, y se ha medido hasta 2,6 s solo para que el
        // sistema rechace una conexion. Con el mismo margen de 3 s que los locales
        // se marcaban como caidos estando perfectamente activos.
        $timeoutRemoto = max(
            $timeout,
            (int) config('virthub.status.probe_timeout_remote_seconds', 8)
        );

        $targets = [];

        foreach ($containers as $name => $container) {
            $esRemoto = $container['host'] !== null && $container['host'] !== '';

            $targets[$name] = [
                'url' => $this->probeUrl($name, (string) $container['url'], $container['host'], $container['port']),
                'timeout' => $esRemoto ? $timeoutRemoto : $timeout,
            ];
        }

        $online = [];

        try {
            $responses = Http::pool(function (Pool $pool) use ($targets): array {
                $pending = [];

                foreach ($targets as $name => $target) {
                    // Un 403 o un 401 no es un fallo de transporte: el escritorio
                    // esta vivo. No se deja que Lance la excepcion.
                    $pending[] = $pool->as((string) $name)
                        ->timeout($target['timeout'])
                        ->withOptions(['http_errors' => false])
                        ->get($target['url']);
                }

                return $pending;
            });

            foreach (array_keys($targets) as $name) {
                $response = $responses[(string) $name] ?? null;

                $online[$name] = $response instanceof \Illuminate\Http\Client\Response
                    && $this->isAlive($response->status());
            }
        } catch (Throwable) {
            // Si el pool entero falla, se deja todo como caido.
        }

        $statuses = [];

        foreach ($containers as $name => $container) {
            $statuses[$name] = [
                'name' => $name,
                'url' => (string) $container['url'],
                'online' => $online[$name] ?? false,
                'capacity' => $container['capacity'],
            ];
        }

        return $statuses;
    }

    /**
     * Un escritorio que contesta esta vivo, aunque rechace la peticion.
     *
     * 401 y 403 significan "estoy en pie, pero no te dejo pasar sin credenciales".
     * Contarlos como caidos era el error que dejaba el panel en rojo.
     */
    private function isAlive(int $status): bool
    {
        if ($status === 401 || $status === 403) {
            return true;
        }

        return $status >= 200 && $status < 400;
    }

    /**
     * Escritorios registrados, en orden de nombre.
     *
     * @return array<string, array{url: string, host: string|null, port: int|null, capacity: int|null}>
     */
    private function registeredContainers(): array
    {
        try {
            $rows = WebtopContainer::query()->orderBy('name')->get();
        } catch (Throwable) {
            return [];
        }

        $containers = [];

        foreach ($rows as $row) {
            $name = trim((string) $row->name);

            if ($name === '') {
                continue;
            }

            $host = trim((string) ($row->internal_host ?? ''));

            $containers[$name] = [
                'url' => (string) $row->url,
                'host' => $host !== '' ? $host : null,
                'port' => $row->internal_port === null ? null : (int) $row->internal_port,
                'capacity' => $row->capacity === null ? null : (int) $row->capacity,
            ];
        }

        return $containers;
    }

    /**
     * Direccion que se sondea para un escritorio.
     *
     * Se prefiere la red interna, porque es la via real por la que Caddy lo
     * alcanza: http://ct0:3000 para los que corren en este equipo.
     *
     * Los escritorios que viven en OTRA maquina declaran su host en
     * 'internal_host' (por ejemplo 192.168.1.9). Antes se sondeaban por nombre
     * (http://ct8:3000), que no resuelve fuera de la red de Docker, y aparecian
     * como caidos aunque estuvieran funcionando.
     *
     * El puerto tambien sale del propio contenedor: los Webtop usan 3000, la
     * maquina virtual con Windows 8006, y los del segundo equipo 3000, 3100,
     * 3200 y 3300.
     */
    private function probeUrl(string $name, string $publicUrl, ?string $host, ?int $port): string
    {
        $port = $port ?? (int) config('virthub.containers.internal_port', 3000);

        // El host declarado manda; si no hay, el nombre del contenedor.
        $destino = $host !== null && $host !== '' ? $host : $name;

        if ($destino !== '' && $port > 0) {
            return sprintf('http://%s:%d/', $destino, $port);
        }

        return $publicUrl;
    }

    private function runCommand(string $command): string
    {
        $output = @shell_exec($command);

        return is_string($output) ? trim($output) : '';
    }
}
