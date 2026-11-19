<?php

namespace App\Services;

use RuntimeException;

/**
 * Libreta digital: notas en markdown, agrupadas por carpetas (materias).
 *
 * Se guarda en storage/app/data/notes.json, igual que el resto de almacenes del
 * proyecto: sin base de datos nueva y facil de copiar o vaciar.
 *
 * Estructura:
 *   users.<usuario>.folders = ["Redes", "Calculo"]
 *   users.<usuario>.notes   = [{ id, title, body, folder, created_at, updated_at }]
 *
 * Una nota con folder vacio esta "sin materia".
 */
class NoteStore
{
    private const MAX_NOTAS = 500;
    private const MAX_CARPETAS = 40;
    private const MAX_TITULO = 120;
    private const MAX_CUERPO = 40000;
    private const MAX_CARPETA = 60;

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

    /**
     * Carpetas del usuario, ordenadas, incluyendo las que ya usan sus notas.
     *
     * @return array<int, string>
     */
    public function folders(string $username): array
    {
        $datos = $this->readData();
        $clave = $this->clave($username);
        $guardadas = $datos['users'][$clave]['folders'] ?? [];

        $carpetas = [];

        foreach ((array) $guardadas as $carpeta) {
            $nombre = $this->limpiarNombre((string) $carpeta);

            if ($nombre !== '') {
                $carpetas[$nombre] = true;
            }
        }

        // Por si una nota apunta a una carpeta que no esta en la lista.
        foreach ($datos['users'][$clave]['notes'] ?? [] as $nota) {
            $nombre = $this->limpiarNombre((string) ($nota['folder'] ?? ''));

            if ($nombre !== '') {
                $carpetas[$nombre] = true;
            }
        }

        $lista = array_keys($carpetas);
        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);

        return $lista;
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

    public function create(string $username, string $title = '', string $body = '', string $folder = ''): array
    {
        return $this->updateData(function (array &$datos) use ($username, $title, $body, $folder): array {
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
                'folder' => $this->limpiarNombre($folder),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            $notas[] = $nota;
            $datos['users'][$clave]['notes'] = $notas;

            return $nota;
        });
    }

    public function update(string $username, string $id, string $title, string $body, ?string $folder = null): array
    {
        return $this->updateData(function (array &$datos) use ($username, $id, $title, $body, $folder): array {
            $clave = $this->clave($username);
            $notas = $datos['users'][$clave]['notes'] ?? [];

            foreach ($notas as $indice => $nota) {
                if (($nota['id'] ?? '') !== $id) {
                    continue;
                }

                $notas[$indice]['title'] = $this->limpiarTitulo($title);
                $notas[$indice]['body'] = $this->limpiarCuerpo($body);
                $notas[$indice]['updated_at'] = date('Y-m-d H:i:s');

                if ($folder !== null) {
                    $notas[$indice]['folder'] = $this->limpiarNombre($folder);
                }

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

    // -----------------------------------------------------------------------
    // Carpetas
    // -----------------------------------------------------------------------

    public function createFolder(string $username, string $name): string
    {
        return $this->updateData(function (array &$datos) use ($username, $name): string {
            $clave = $this->clave($username);
            $nombre = $this->limpiarNombre($name);

            if ($nombre === '') {
                throw new RuntimeException('La carpeta necesita un nombre.');
            }

            $carpetas = (array) ($datos['users'][$clave]['folders'] ?? []);

            if (count($carpetas) >= self::MAX_CARPETAS) {
                throw new RuntimeException('Ya hay demasiadas carpetas.');
            }

            foreach ($carpetas as $existente) {
                if (mb_strtolower((string) $existente) === mb_strtolower($nombre)) {
                    throw new RuntimeException('Ya existe una carpeta con ese nombre.');
                }
            }

            $carpetas[] = $nombre;
            $datos['users'][$clave]['folders'] = $carpetas;

            return $nombre;
        });
    }

    /**
     * Borra la carpeta. Las notas que tenia NO se borran: se quedan sin materia.
     */
    public function deleteFolder(string $username, string $name): int
    {
        return $this->updateData(function (array &$datos) use ($username, $name): int {
            $clave = $this->clave($username);
            $nombre = $this->limpiarNombre($name);

            $carpetas = array_values(array_filter(
                (array) ($datos['users'][$clave]['folders'] ?? []),
                static fn ($carpeta): bool => mb_strtolower((string) $carpeta) !== mb_strtolower($nombre)
            ));

            $datos['users'][$clave]['folders'] = $carpetas;

            $movidas = 0;
            $notas = $datos['users'][$clave]['notes'] ?? [];

            foreach ($notas as $indice => $nota) {
                if (mb_strtolower((string) ($nota['folder'] ?? '')) !== mb_strtolower($nombre)) {
                    continue;
                }

                $notas[$indice]['folder'] = '';
                $movidas++;
            }

            $datos['users'][$clave]['notes'] = $notas;

            return $movidas;
        });
    }

    public function renameFolder(string $username, string $old, string $new): string
    {
        return $this->updateData(function (array &$datos) use ($username, $old, $new): string {
            $clave = $this->clave($username);
            $viejo = $this->limpiarNombre($old);
            $nuevo = $this->limpiarNombre($new);

            if ($nuevo === '') {
                throw new RuntimeException('La carpeta necesita un nombre.');
            }

            $carpetas = (array) ($datos['users'][$clave]['folders'] ?? []);

            foreach ($carpetas as $indice => $carpeta) {
                if (mb_strtolower((string) $carpeta) === mb_strtolower($viejo)) {
                    $carpetas[$indice] = $nuevo;
                }
            }

            $datos['users'][$clave]['folders'] = array_values(array_unique($carpetas));

            $notas = $datos['users'][$clave]['notes'] ?? [];

            foreach ($notas as $indice => $nota) {
                if (mb_strtolower((string) ($nota['folder'] ?? '')) === mb_strtolower($viejo)) {
                    $notas[$indice]['folder'] = $nuevo;
                }
            }

            $datos['users'][$clave]['notes'] = $notas;

            return $nuevo;
        });
    }

    // -----------------------------------------------------------------------
    // Limpieza y almacenamiento
    // -----------------------------------------------------------------------

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

    private function limpiarNombre(string $nombre): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $nombre) ?? ''), 0, self::MAX_CARPETA);
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

            // Antes de escribir, se guarda una copia de lo que habia. Si algo
            // sale mal (o alguien borra el archivo), la libreta se puede
            // recuperar desde notes.json.bak.
            $this->guardarRespaldo($contenido);

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

    /**
     * Copia el contenido anterior a notes.json.bak (y rota la copia previa).
     *
     * Es una red de seguridad barata: la libreta es un solo archivo y perderlo
     * es perder todas las notas de todas las personas.
     */
    private function guardarRespaldo(string $contenido): void
    {
        if (trim($contenido) === '' || json_decode($contenido, true) === null) {
            return;
        }

        $respaldo = $this->filePath . '.bak';

        if (is_file($respaldo)) {
            @copy($respaldo, $respaldo . '.1');
        }

        @file_put_contents($respaldo, $contenido);
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
