<?php

namespace App\Support;

use App\Services\WebtopAllocator;
use Illuminate\Support\Facades\DB;

/**
 * Decide a que contenedor Webtop pertenece cada usuario.
 *
 * La asignacion real vive en la base de datos (workspace_assignments), con un
 * indice unico sobre user_id: cada usuario tiene su propio escritorio y no se
 * comparte con nadie.
 *
 * El reparto por hash se conserva SOLO como respaldo para cuando no hay
 * contenedores declarados en la base de datos (una instalacion que aun no ha
 * sincronizado la infraestructura) o no queda capacidad. Asi una instalacion
 * existente sigue funcionando mientras se migra.
 */
class ContainerResolver
{
    public function __construct(private readonly WebtopAllocator $allocator) {}

    /**
     * @param  array<string, mixed>|null  $user
     */
    public function urlFor(?array $user): string
    {
        if ($user === null) {
            return $this->urlForIndex($this->guestIndex());
        }

        $role = (string) ($user['role'] ?? 'user');

        if ($role === 'guest') {
            return $this->urlForIndex($this->guestIndex());
        }

        $username = (string) ($user['username'] ?? '');
        $userId = $this->resolveUserId($user);

        // El contenedor asignado tiene prioridad: es el que garantiza el
        // aislamiento entre usuarios.
        if ($userId !== null) {
            $container = $this->allocator->containerFor($userId);

            if ($container !== null && trim((string) $container->url) !== '') {
                return (string) $container->url;
            }
        }

        // Respaldo: reparto determinista sobre la lista de configuracion.
        if ($this->isPrivilegedContainerUser($username)) {
            return $this->urlForIndex(0);
        }

        return $this->urlForIndex($this->poolIndexFor($username));
    }

    /**
     * Contenedor asignado al usuario, si tiene uno.
     */
    public function assignedContainerFor(array $user): ?string
    {
        $userId = $this->resolveUserId($user);

        if ($userId === null) {
            return null;
        }

        $container = $this->allocator->currentContainer($userId);

        return $container?->url;
    }

    public function urlForIndex(int $index): string
    {
        $urls = (array) config('virthub.containers.urls', []);

        return (string) ($urls[$index] ?? 'https://ct' . $index . '.virthub.dpdns.org/');
    }

    public function guestIndex(): int
    {
        return (int) config('virthub.containers.guest_index', 7);
    }

    /**
     * Reparto determinista de respaldo.
     *
     * Se usa solo cuando no hay asignacion posible, de modo que el usuario
     * conserva el mismo escritorio entre sesiones.
     */
    public function poolIndexFor(string $username): int
    {
        $start = (int) config('virthub.containers.pool_start', 2);
        $end = (int) config('virthub.containers.pool_end', 6);
        $size = max(1, ($end - $start) + 1);

        return ($this->stableHash($username) % $size) + $start;
    }

    public function isPrivilegedContainerUser(string $username): bool
    {
        /** @var array<int, string> $adminUsers */
        $adminUsers = (array) config('virthub.containers.admin_users', []);

        return in_array($username, $adminUsers, true);
    }

    /**
     * El id del usuario es necesario para la asignacion de escritorios.
     *
     * Debe ser un entero porque workspace_assignments.user_id es una clave
     * foranea a users.id. El almacen JSON genera UUIDs como id, asi que un UUID
     * no sirve: en ese caso se consulta la tabla de usuarios por username, y si
     * tampoco esta alli se devuelve null y se usa el respaldo por hash.
     *
     * @param  array<string, mixed>  $user
     */
    private function resolveUserId(array $user): ?int
    {
        $id = $user['id'] ?? null;

        if ($id !== null && $id !== '' && is_numeric($id)) {
            return (int) $id;
        }

        // El id no es utilizable (ausente o UUID): se busca la fila real.
        $username = trim((string) ($user['username'] ?? ''));

        if ($username === '') {
            return null;
        }

        try {
            $row = DB::table('users')->where('username', $username)->value('id');

            return $row === null ? null : (int) $row;
        } catch (\Throwable $e) {
            // Sin base de datos disponible no hay asignacion posible.
            return null;
        }
    }

    /**
     * Hash estable entre versiones y plataformas.
     *
     * crc32() puede devolver un entero negativo en PHP de 32 bits y firmar
     * distinto segun la plataforma; el modulo con signo repartiria mal y
     * romperia la persistencia del escritorio. Normalizamos con sprintf('%u').
     */
    private function stableHash(string $username): int
    {
        return (int) sprintf('%u', crc32($username));
    }
}
