<?php

namespace App\Services;

use RuntimeException;

/**
 * Avisos para el usuario.
 *
 * Un aviso es un mensaje corto que la aplicacion le deja a alguien: "tu foto se
 * retiro por contenido no permitido", por ejemplo. Se guardan en un JSON como el
 * resto del proyecto y se muestran como una banda arriba de la pagina hasta que
 * el destinatario los marca como leidos.
 *
 * Cada aviso va dirigido a un nombre de usuario ('admin' incluido), asi que el
 * administrador recibe los suyos igual que cualquier otra persona.
 */
class AvisoStore
{
    private string $filePath;

    private int $maximo = 200;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/avisos.json');
    }

    /**
     * Deja un aviso a un usuario.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function crear(string $para, string $titulo, string $mensaje, string $tipo = 'info', array $extra = []): array
    {
        $para = trim($para);

        if ($para === '') {
            throw new RuntimeException('El aviso necesita un destinatario.');
        }

        return $this->actualizar(function (array &$datos) use ($para, $titulo, $mensaje, $tipo, $extra): array {
            $aviso = [
                'id' => $this->siguienteId($datos),
                'para' => $para,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => $tipo,
                'extra' => $extra,
                'leido' => false,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $datos['avisos'][] = $aviso;
            $datos['avisos'] = array_slice($datos['avisos'], -$this->maximo);

            return $aviso;
        });
    }

    /**
     * Avisos de un usuario. Por defecto solo los no leidos.
     *
     * @return list<array<string, mixed>>
     */
    public function para(string $usuario, bool $incluirLeidos = false): array
    {
        $aviso = [];

        foreach ($this->leer() as $entrada) {
            if (($entrada['para'] ?? '') !== $usuario) {
                continue;
            }

            if (! $incluirLeidos && ! empty($entrada['leido'])) {
                continue;
            }

            $aviso[] = $entrada;
        }

        return array_reverse($aviso);
    }

    /**
     * Marca como leidos los avisos de un usuario. Sin id, todos.
     */
    public function marcarLeido(string $usuario, ?int $id = null): int
    {
        return $this->actualizar(function (array &$datos) use ($usuario, $id): int {
            $marcados = 0;

            foreach ($datos['avisos'] as $indice => $entrada) {
                if (($entrada['para'] ?? '') !== $usuario) {
                    continue;
                }

                if ($id !== null && (int) ($entrada['id'] ?? 0) !== $id) {
                    continue;
                }

                if (empty($entrada['leido'])) {
                    $datos['avisos'][$indice]['leido'] = true;
                    $marcados++;
                }
            }

            return $marcados;
        });
    }

    public function noLeidos(string $usuario): int
    {
        return count($this->para($usuario));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leer(): array
    {
        if (! is_file($this->filePath)) {
            return [];
        }

        $contenido = file_get_contents($this->filePath);
        $datos = json_decode($contenido !== false ? $contenido : '', true);

        return is_array($datos['avisos'] ?? null) ? $datos['avisos'] : [];
    }

    /**
     * @param callable(array<string, mixed>&): mixed $callback
     */
    private function actualizar(callable $callback): mixed
    {
        $this->prepararArchivo();

        $handle = fopen($this->filePath, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo de avisos.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el archivo de avisos.');
            }

            rewind($handle);
            $contenido = stream_get_contents($handle);
            $decodificado = json_decode($contenido !== false ? $contenido : '', true);

            $datos = is_array($decodificado) ? $decodificado : [];
            $datos['avisos'] = is_array($datos['avisos'] ?? null) ? $datos['avisos'] : [];

            $resultado = $callback($datos);

            $codificado = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($codificado === false) {
                throw new RuntimeException('No se pudieron guardar los avisos.');
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $codificado);
            fflush($handle);

            return $resultado;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function siguienteId(array $datos): int
    {
        $mayor = 0;

        foreach ($datos['avisos'] as $entrada) {
            $mayor = max($mayor, (int) ($entrada['id'] ?? 0));
        }

        return $mayor + 1;
    }

    private function prepararArchivo(): void
    {
        $dir = dirname($this->filePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode(
                ['avisos' => []],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));
        }
    }
}
