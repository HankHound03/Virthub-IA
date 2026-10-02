<?php

namespace App\Support;

/**
 * Decide a que contenedor Webtop pertenece cada usuario.
 *
 * Sustituye a la funcion global virthub_get_container_url() para que la regla
 * de asignacion viva en un solo lugar y sea testeable.
 */
class ContainerResolver
{
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

        if ($this->isPrivilegedContainerUser($username)) {
            return $this->urlForIndex(0);
        }

        return $this->urlForIndex($this->poolIndexFor($username));
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
     * Reparto determinista: el mismo usuario cae siempre en el mismo contenedor
     * del grupo, de modo que su escritorio conserva el estado entre sesiones.
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
