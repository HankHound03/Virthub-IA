<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Acceso al usuario activo ya resuelto por el middleware ResolveActiveUser.
 */
final class ActiveUser
{
    public const ATTRIBUTE = 'virthub.user';

    /**
     * @return array<string, mixed>|null
     */
    public static function from(Request $request): ?array
    {
        $user = $request->attributes->get(self::ATTRIBUTE);

        return is_array($user) ? $user : null;
    }

    public static function isGuest(?array $user): bool
    {
        return ($user['role'] ?? null) === 'guest';
    }

    public static function isRegistered(?array $user): bool
    {
        return $user !== null && ! self::isGuest($user);
    }

    public static function isAdmin(?array $user): bool
    {
        return ($user['role'] ?? null) === 'admin';
    }

    public static function username(?array $user): string
    {
        return (string) ($user['username'] ?? '');
    }
}
