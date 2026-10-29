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
        $gpu = (array) ($datos['gpu'] ?? $datos);

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
