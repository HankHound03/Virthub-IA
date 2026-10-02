<?php

namespace App\Support;

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
     * @return array<int, bool>
     */
    private function containerStatuses(int $timeout): array
    {
        /** @var array<int, int> $indices */
        $indices = (array) config('virthub.containers.indices', []);
        $urls = (array) config('virthub.containers.urls', []);

        $targets = [];
        foreach ($indices as $index) {
            $index = (int) $index;
            $url = (string) ($urls[$index] ?? '');

            if ($url !== '') {
                $targets[$index] = $url;
            }
        }

        $statuses = array_fill_keys(array_map('intval', $indices), false);

        if ($targets === []) {
            return $statuses;
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($targets, $timeout): array {
                $pending = [];

                foreach ($targets as $index => $url) {
                    $pending[] = $pool->as((string) $index)->timeout($timeout)->get($url);
                }

                return $pending;
            });

            foreach (array_keys($targets) as $index) {
                $response = $responses[(string) $index] ?? null;

                if ($response instanceof \Illuminate\Http\Client\Response) {
                    $statuses[$index] = $response->successful();
                }
            }
        } catch (Throwable) {
            // Un fallo del pool deja todos los contenedores como no disponibles.
        }

        return $statuses;
    }

    private function runCommand(string $command): string
    {
        $output = @shell_exec($command);

        return is_string($output) ? trim($output) : '';
    }
}
