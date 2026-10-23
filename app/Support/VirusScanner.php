<?php

namespace App\Support;

use RuntimeException;
use Throwable;

/**
 * Analisis de archivos subidos con ClamAV.
 *
 * Se habla con el daemon `clamd` por socket TCP usando el comando INSTREAM: se
 * le manda el archivo por trozos y responde si esta limpio o con el nombre del
 * virus encontrado.
 *
 * Por que por socket y no con el binario: el binario vive en el contenedor de
 * ClamAV, no en el de la aplicacion, y ademas INSTREAM evita tener que compartir
 * el sistema de archivos entre los dos contenedores.
 *
 * Nada de lo que se sube sale del servidor.
 */
class VirusScanner
{
    /** Tamano de cada trozo enviado al daemon (ClamAV recomienda 8-64 KB). */
    private const TROZO = 65536;

    public function habilitado(): bool
    {
        return (bool) config('virthub.antivirus.enabled', true);
    }

    /**
     * Escanea un archivo del disco.
     *
     * Devuelve siempre la misma forma, sin lanzar excepciones:
     *
     *   ['limpio' => bool, 'virus' => string|null, 'error' => string|null]
     *
     * Si el antivirus no esta disponible, 'limpio' depende de fail_open: con
     * true se deja pasar (y se anota el motivo en 'error' para el registro).
     *
     * @return array{limpio: bool, virus: string|null, error: string|null}
     */
    public function escanear(string $ruta): array
    {
        if (! $this->habilitado()) {
            return ['limpio' => true, 'virus' => null, 'error' => null];
        }

        if (! is_file($ruta) || ! is_readable($ruta)) {
            return ['limpio' => false, 'virus' => null, 'error' => 'No se puede leer el archivo.'];
        }

        try {
            return $this->consultarDaemon($ruta);
        } catch (Throwable $e) {
            // El antivirus caido no debe tumbar el foro, salvo que se haya
            // configurado lo contrario.
            $dejarPasar = (bool) config('virthub.antivirus.fail_open', true);

            logger()->warning('Antivirus no disponible: ' . $e->getMessage(), [
                'archivo' => basename($ruta),
                'se_permitio' => $dejarPasar,
            ]);

            return [
                'limpio' => $dejarPasar,
                'virus' => null,
                'error' => 'Antivirus no disponible.',
            ];
        }
    }

    /**
     * Interpreta la respuesta del daemon.
     *
     * Se separa del socket para poder probarla sin un ClamAV delante.
     *
     *   "stream: OK"                            -> limpio
     *   "stream: Eicar-Signature FOUND"         -> infectado
     *   "INSTREAM size limit exceeded. ERROR"   -> error
     *
     * @return array{limpio: bool, virus: string|null, error: string|null}
     */
    public static function interpretar(string $respuesta): array
    {
        $respuesta = trim($respuesta);

        if ($respuesta === '') {
            return ['limpio' => false, 'virus' => null, 'error' => 'Respuesta vacia del antivirus.'];
        }

        // Formato habitual: "stream: <resultado>".
        $resultado = $respuesta;
        if (str_starts_with($respuesta, 'stream:')) {
            $resultado = trim(substr($respuesta, strlen('stream:')));
        }

        if (strcasecmp($resultado, 'OK') === 0) {
            return ['limpio' => true, 'virus' => null, 'error' => null];
        }

        if (str_contains($resultado, 'FOUND')) {
            $virus = trim(str_replace('FOUND', '', $resultado));

            return ['limpio' => false, 'virus' => $virus !== '' ? $virus : 'Desconocido', 'error' => null];
        }

        return ['limpio' => false, 'virus' => null, 'error' => $resultado];
    }

    /**
     * Envia el archivo al daemon y devuelve el resultado ya interpretado.
     *
     * @return array{limpio: bool, virus: string|null, error: string|null}
     */
    private function consultarDaemon(string $ruta): array
    {
        $host = (string) config('virthub.antivirus.host', 'clamav');
        $puerto = (int) config('virthub.antivirus.port', 3310);
        $timeout = max(5, (int) config('virthub.antivirus.timeout', 60));

        $socket = @fsockopen($host, $puerto, $errno, $errstr, min(10, $timeout));

        if ($socket === false) {
            throw new RuntimeException("No se pudo conectar con clamd ({$host}:{$puerto}): {$errstr}");
        }

        try {
            stream_set_timeout($socket, $timeout);

            // Comando. El "z" final indica que termina en byte nulo.
            fwrite($socket, "zINSTREAM\0");

            $entrada = fopen($ruta, 'rb');

            if ($entrada === false) {
                throw new RuntimeException('No se pudo abrir el archivo para escanear.');
            }

            while (! feof($entrada)) {
                $trozo = fread($entrada, self::TROZO);

                if ($trozo === false || $trozo === '') {
                    break;
                }

                // INSTREAM espera: 4 bytes con el tamano + los datos.
                fwrite($socket, pack('N', strlen($trozo)) . $trozo);
            }

            fclose($entrada);

            // Fin del envio: un trozo de tamano 0.
            fwrite($socket, pack('N', 0));

            $respuesta = fgets($socket, 4096);

            if ($respuesta === false) {
                throw new RuntimeException('El antivirus no respondio.');
            }

            return self::interpretar($respuesta);
        } finally {
            fclose($socket);
        }
    }
}
