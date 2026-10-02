<?php

namespace App\Http\Middleware;

use App\Services\UserStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el usuario activo una sola vez por peticion y lo deja en los
 * atributos del request.
 *
 * Antes cada ruta llamaba a virthub_active_user($request, $users) por su cuenta
 * (44 veces). Aqui vive la unica definicion de "sesion valida":
 *  - el invitado caduca segun guest_expires_at;
 *  - la cuenta registrada debe seguir existiendo y estar activa.
 */
class ResolveActiveUser
{
    public function __construct(private readonly UserStore $users) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('virthub.user', $this->resolve($request));

        return $next($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolve(Request $request): ?array
    {
        $sessionUser = $request->session()->get('auth_user');

        if (! is_array($sessionUser) || ! isset($sessionUser['username'])) {
            return null;
        }

        if (($sessionUser['role'] ?? '') === 'guest') {
            return $this->resolveGuest($request, $sessionUser);
        }

        return $this->resolveRegistered($request, (string) $sessionUser['username']);
    }

    /**
     * @param  array<string, mixed>  $sessionUser
     * @return array<string, mixed>|null
     */
    private function resolveGuest(Request $request, array $sessionUser): ?array
    {
        $expiresAt = (int) $request->session()->get('guest_expires_at', 0);

        if ($expiresAt <= 0 || time() > $expiresAt) {
            $this->invalidate($request);

            return null;
        }

        return [
            'username' => (string) $sessionUser['username'],
            'role' => 'guest',
            'profile_image_path' => null,
            'profile_frame_color' => '#6ea8ff',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveRegistered(Request $request, string $username): ?array
    {
        $user = $this->users->findByUsername($username);

        if (! $user || ! ($user['is_active'] ?? true)) {
            $this->invalidate($request);

            return null;
        }

        return [
            // El id se propaga porque la asignacion de escritorios lo necesita:
            // workspace_assignments referencia users.id, no el username.
            'id' => (string) ($user['id'] ?? ''),
            'name' => (string) ($user['name'] ?? $user['username'] ?? ''),
            'username' => (string) $user['username'],
            'role' => (string) ($user['role'] ?? 'user'),
            'profile_image_path' => $user['profile_image_path'] ?? null,
            'profile_frame_color' => $user['profile_frame_color'] ?? '#6ea8ff',
        ];
    }

    private function invalidate(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
