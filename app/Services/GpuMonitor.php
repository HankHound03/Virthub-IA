<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lee el uso de la GPU del equipo anfitrion.
 *
 * La aplicacion corre dentro de Docker y ahi no existe nvidia-smi, asi que no
 * puede preguntarle a la tarjeta directamente. En el equipo se ejecuta un pequeno
 * puente (tools/gpu-bridge.ps1) que responde con lo que dice nvidia-smi, y esta
 * clase lo consulta por HTTP.
 *
 * Si el puente no esta levantado, devuelve null y el panel simplemente no muestra
 * datos de GPU: nunca debe romper la pagina por no poder leer la tarjeta.
 */
class GpuMonitor
{
    public function habilitado(): bool
    {
        return (bool) config('virthub.ia.monitor', false);
    }

    /**
     * @return array{name: string, util: int, mem_used: int, mem_total: int, temp: int}|null
     */
    public function leer(): ?array
    {
        if (! $this->habilitado()) {
            return null;
        }

        /*
         * Primero el archivo.
         *
         * El puente del equipo escribe las cifras en storage/app/gpu/gpu.json y el
         * contenedor lo tiene montado en solo lectura. Asi no hay ningun puerto
         * abierto: nada que escanear ni que cerrar en el firewall.
         */
        $desdeArchivo = $this->leerArchivo();

        if ($desdeArchivo !== null) {
            return $desdeArchivo;
        }

        // Compatibilidad: si alguien corre el puente con -Http, sigue funcionando.
        return $this->leerPorHttp();
    }

    /**
     * Lee las cifras del archivo que deja el puente.
     *
     * @return array{name: string, util: int, mem_used: int, mem_total: int, temp: int}|null
     */
    private function leerArchivo(): ?array
    {
        $ruta = (string) config('virthub.ia.gpu_archivo', storage_path('app/gpu/gpu.json'));

        if ($ruta === '' || ! is_file($ruta) || ! is_readable($ruta)) {
            return null;
        }

        /*
         * Si el puente lleva rato detenido, el archivo se queda con las ultimas
         * cifras. Mostrarlas seria mentir: se descarta lo que tenga mas de un
         * minuto, que es margen de sobra para dos segundos de intervalo.
         */
        $edad = time() - (int) @filemtime($ruta);

        if ($edad > 60) {
            return null;
        }

        $crudo = @file_get_contents($ruta);

        if (! is_string($crudo) || $crudo === '') {
            return null;
        }

        $datos = json_decode($crudo, true);

        if (! is_array($datos) || ! ($datos['ok'] ?? false)) {
            return null;
        }

        return $this->normalizar((array) ($datos['gpu'] ?? []));
    }

    /**
     * @return array{name: string, util: int, mem_used: int, mem_total: int, temp: int}|null
     */
    private function leerPorHttp(): ?array
    {
        $url = trim((string) config('virthub.ia.gpu_url', ''));

        if ($url === '') {
            return null;
        }

        try {
            $respuesta = Http::timeout(2)
                ->connectTimeout(1)
                ->acceptJson()
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        $datos = (array) $respuesta->json();

        return $this->normalizar((array) ($datos['gpu'] ?? $datos));
    }

    /**
     * Da la misma forma a las cifras vengan del archivo o de HTTP.
     *
     * @param  array<string, mixed>  $gpu
     * @return array{name: string, util: int, mem_used: int, mem_total: int, temp: int}|null
     */
    private function normalizar(array $gpu): ?array
    {
        if (! isset($gpu['util'])) {
            return null;
        }

        return [
            'name' => (string) ($gpu['name'] ?? 'GPU'),
            'util' => (int) $gpu['util'],
            'mem_used' => (int) ($gpu['mem_used'] ?? 0),
            'mem_total' => (int) ($gpu['mem_total'] ?? 0),
            'temp' => (int) ($gpu['temp'] ?? 0),
        ];
    }
}
