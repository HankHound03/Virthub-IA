<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Moderacion de imagenes con NSFW Sherlock.
 *
 * Sherlock es un servicio aparte (imagen Docker m1chl/nsfw-sherlock) que recibe
 * una imagen en base64 y devuelve si es contenido no apto. Aqui solo se habla con
 * el y se traduce su respuesta a una decision: dejar pasar o tumbar la foto.
 *
 * La moderacion es probabilistica: no es una verdad absoluta. Por eso hay un
 * umbral configurable y, si el servicio no responde, se puede elegir entre dejar
 * pasar la foto (por defecto) o bloquearla.
 */
class ModerationService
{
    /** Estados posibles de una revision. */
    public const APTA = 'apta';
    public const PROHIBIDA = 'prohibida';
    public const SIN_REVISION = 'sin_revision';

    public function habilitado(): bool
    {
        return (bool) config('virthub.moderacion.enabled', false);
    }

    /**
     * Revisa una imagen.
     *
     * @return array{estado: string, prohibida: bool, puntuacion: float, motivo: string, detalle: array<string, mixed>}
     */
    public function revisarImagen(string $rutaAbsoluta, string $nombre): array
    {
        if (! $this->habilitado()) {
            return $this->sinRevision('La moderacion esta apagada.');
        }

        if (! is_file($rutaAbsoluta) || ! is_readable($rutaAbsoluta)) {
            return $this->sinRevision('No se pudo leer la imagen para revisarla.');
        }

        $contenido = @file_get_contents($rutaAbsoluta);

        if ($contenido === false || $contenido === '') {
            return $this->sinRevision('La imagen esta vacia.');
        }

        $url = rtrim((string) config('virthub.moderacion.url', 'http://nsfw-sherlock:4000'), '/');
        $timeout = (int) config('virthub.moderacion.timeout', 25);
        $umbral = (float) config('virthub.moderacion.umbral', 0.75);

        try {
            $respuesta = Http::timeout($timeout)
                ->acceptJson()
                ->post($url . '/pic/analyze', [
                    'base64' => base64_encode($contenido),
                    'filename' => $nombre,
                ]);
        } catch (Throwable $e) {
            Log::warning('Moderacion NSFW: el servicio no respondio.', [
                'archivo' => $nombre,
                'error' => $e->getMessage(),
            ]);

            return $this->sinRevision('El servicio de moderacion no respondio.', [
                'error' => $e->getMessage(),
            ]);
        }

        if (! $respuesta->successful()) {
            Log::warning('Moderacion NSFW: respuesta inesperada.', [
                'archivo' => $nombre,
                'estado' => $respuesta->status(),
            ]);

            return $this->sinRevision('El servicio de moderacion devolvio un error.', [
                'estado' => $respuesta->status(),
            ]);
        }

        $datos = (array) $respuesta->json();

        /*
         * Formato real de Sherlock (/pic/analyze):
         *
         *   {
         *     "verdict": "sfw",
         *     "nsfw": 0.0003,
         *     "labels": {"porn": 0.0, "hentai": 0.0, "sexy": 0.0, "neutral": 0.99},
         *     "detections": [...],
         *     "status": "ok"
         *   }
         *
         * Se usa el veredicto si viene, y la puntuacion como respaldo.
         */
        $veredicto = strtolower(trim((string) ($datos['verdict'] ?? '')));
        $puntuacion = (float) ($datos['nsfw'] ?? 0.0);

        // Si el formato cambia y no hay "verdict", se busca de forma flexible.
        if ($veredicto === '') {
            $puntuacion = $puntuacion > 0 ? $puntuacion : $this->extraerPuntuacion($datos);

            $prohibida = $this->extraerVeredicto($datos) ?? ($puntuacion >= $umbral);
        } else {
            $prohibida = in_array($veredicto, ['nsfw', 'explicit', 'unsafe', 'porn'], true)
                || $puntuacion >= $umbral;
        }

        // Una deteccion de zonas explicitas tambien cuenta como contenido no apto.
        if (! $prohibida && ! empty($datos['detections'])) {
            $prohibida = true;
        }

        return [
            'estado' => $prohibida ? self::PROHIBIDA : self::APTA,
            'prohibida' => $prohibida,
            'puntuacion' => round($puntuacion, 4),
            'motivo' => $prohibida
                ? 'Contenido no permitido detectado automaticamente.'
                : '',
            'detalle' => $datos,
        ];
    }

    /**
     * Busca el veredicto booleano en la respuesta, sea cual sea su forma.
     *
     * Sherlock ha cambiado de formato entre versiones, asi que se aceptan varios
     * nombres. Si no aparece ninguno, se decide por la puntuacion.
     */
    private function extraerVeredicto(array $datos): ?bool
    {
        $claves = [
            'is_nsfw', 'nsfw', 'isNSFW', 'explicit', 'is_explicit',
            'flagged', 'is_flagged', 'unsafe', 'is_unsafe',
            'porn', 'is_porn', 'suggestive', 'is_suggestive',
        ];

        foreach ($this->recorrer($datos) as $clave => $valor) {
            if (in_array($clave, $claves, true) && is_bool($valor)) {
                return $valor;
            }
        }

        return null;
    }

    /**
     * Busca la puntuacion de contenido no apto (0 a 1).
     */
    private function extraerPuntuacion(array $datos): float
    {
        $claves = [
            'nsfw_score', 'score', 'nsfw_probability', 'nsfwProbability',
            'probability', 'confidence', 'explicit_score', 'porn_score',
        ];

        $candidatos = [];

        foreach ($this->recorrer($datos) as $clave => $valor) {
            if (! is_numeric($valor)) {
                continue;
            }

            if (in_array($clave, $claves, true)) {
                $candidatos[] = (float) $valor;
            }
        }

        if ($candidatos !== []) {
            return max($candidatos);
        }

        // Algunos formatos devuelven las probabilidades por clase
        // (por ejemplo: {"porn": 0.02, "sexy": 0.4, "neutral": 0.58}).
        // 'sexy' se deja fuera a proposito: marca playa, trajes de bano y demas.
        $clases = ['porn', 'explicit', 'nsfw', 'hentai'];
        $mayor = 0.0;

        foreach ($this->recorrer($datos) as $clave => $valor) {
            if (is_numeric($valor) && in_array($clave, $clases, true)) {
                $mayor = max($mayor, (float) $valor);
            }
        }

        return $mayor;
    }

    /**
     * Recorre el JSON anidado y devuelve clave => valor de las hojas.
     *
     * @return iterable<string, mixed>
     */
    private function recorrer(array $datos): iterable
    {
        foreach ($datos as $clave => $valor) {
            if (is_array($valor)) {
                yield from $this->recorrer($valor);

                continue;
            }

            yield (string) $clave => $valor;
        }
    }

    /**
     * @param array<string, mixed> $detalle
     * @return array{estado: string, prohibida: bool, puntuacion: float, motivo: string, detalle: array<string, mixed>}
     */
    private function sinRevision(string $motivo, array $detalle = []): array
    {
        return [
            'estado' => self::SIN_REVISION,
            'prohibida' => false,
            'puntuacion' => 0.0,
            'motivo' => $motivo,
            'detalle' => $detalle,
        ];
    }
}
