<?php

namespace App\Services;

use RuntimeException;

/**
 * Notas de la libreta digital, una por persona.
 *
 * Se guardan en storage/app/data/notes.json, igual que el resto de almacenes del
 * proyecto: sin base de datos nueva y facil de copiar o vaciar.
 *
 * Cada nota es:
 *   id, title, body (markdown), created_at, updated_at
 */
class NoteStore
{
    private const MAX_NOTAS = 200;
    private const MAX_TITULO = 120;
    private const MAX_CUERPO = 40000;

    private string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/notes.json');
    }

    /** @return array<int, array<string, mixed>> */
    public function all(string $username): array
    {
        $datos = $this->readData();
        $notas = $datos['users'][$this->clave($username)]['notes'] ?? [];

        // La mas reciente primero.
        usort($notas, static fn (array $a, array $b): int => strcmp(
            (string) ($b['updated_at'] ?? ''),
            (string) ($a['updated_at'] ?? '')
        ));

        return $notas;
    }

    public function find(string $username, string $id): ?array
    {
        foreach ($this->all($username) as $nota) {
            if (($nota['id'] ?? '') === $id) {
                return $nota;
            }
        }

        return null;
    }

    public function create(string $username, string $title = '', string $body = ''): array
    {
        return $this->updateData(function (array &$datos) use ($username, $title, $body): array {
            $clave = $this->clave($username);
            $notas = $datos['users'][$clave]['notes'] ?? [];

            if (count($notas) >= self::MAX_NOTAS) {
                throw new RuntimeException('La libreta esta llena. Borra alguna nota para crear otra.');
            }

            $ahora = date('Y-m-d H:i:s');

            $nota = [
                'id' => bin2hex(random_bytes(8)),
                'title' => $this->limpiarTitulo($title),
                'body' => $this->limpiarCuerpo($body),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            $notas[] = $nota;
            $datos['users'][$clave]['notes'] = $notas;

            return $nota;
        });
    }

    public function update(string $username, string $id, string $title, string $body): array
    {
        return $this->updateData(function (array &$datos) use ($username, $id, $title, $body): array {
            $clave = $this->clave($username);
            $notas = $datos['users'][$clave]['notes'] ?? [];

            foreach ($notas as $indice => $nota) {
                if (($nota['id'] ?? '') !== $id) {
                    continue;
                }

                $notas[$indice]['title'] = $this->limpiarTitulo($title);
                $notas[$indice]['body'] = $this->limpiarCuerpo($body);
                $notas[$indice]['updated_at'] = date('Y-m-d H:i:s');

                $datos['users'][$clave]['notes'] = $notas;

                return $notas[$indice];
            }

            throw new RuntimeException('Esa nota no existe.');
        });
    }

    public function delete(string $username, string $id): void
    {
        $this->updateData(function (array &$datos) use ($username, $id): array {
            $clave = $this->clave($username);
            $notas = $datos['users'][$clave]['notes'] ?? [];

            $quedan = array_values(array_filter(
                $notas,
                static fn (array $nota): bool => ($nota['id'] ?? '') !== $id
            ));

            if (count($quedan) === count($notas)) {
                throw new RuntimeException('Esa nota no existe.');
            }

            $datos['users'][$clave]['notes'] = $quedan;

            return ['ok' => true];
        });
    }

    private function limpiarTitulo(string $titulo): string
    {
        $titulo = trim(preg_replace('/\s+/', ' ', $titulo) ?? '');

        if ($titulo === '') {
            return 'Sin titulo';
        }

        return mb_substr($titulo, 0, self::MAX_TITULO);
    }

    private function limpiarCuerpo(string $cuerpo): string
    {
        // Se guarda tal cual, incluidos los saltos de linea del markdown.
        return mb_substr(str_replace("\r\n", "\n", $cuerpo), 0, self::MAX_CUERPO);
    }

    private function clave(string $username): string
    {
        return mb_strtolower(trim($username)) ?: 'anonimo';
    }

    /** @return array<string, mixed> */
    private function readData(): array
    {
        $this->ensureStore();

        $contenido = file_get_contents($this->filePath);

        if ($contenido === false || trim($contenido) === '') {
            return ['users' => []];
        }

        $datos = json_decode($contenido, true);

        if (!is_array($datos)) {
            return ['users' => []];
        }

        $datos['users'] = is_array($datos['users'] ?? null) ? $datos['users'] : [];

        return $datos;
    }

    private function updateData(callable $callback): mixed
    {
        $this->ensureStore();

        $handle = fopen($this->filePath, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir la libreta.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear la libreta.');
            }

            $contenido = stream_get_contents($handle);
            $datos = json_decode((string) $contenido, true);
            $datos = is_array($datos) ? $datos : ['users' => []];
            $datos['users'] = is_array($datos['users'] ?? null) ? $datos['users'] : [];

            $resultado = $callback($datos);

            $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                throw new RuntimeException('No se pudo preparar la libreta para guardar.');
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $json . "\n");
            fflush($handle);

            return $resultado;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureStore(): void
    {
        $carpeta = dirname($this->filePath);

        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta de la libreta.');
        }

        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode(['users' => []], JSON_PRETTY_PRINT) . "\n");
        }
    }
}
