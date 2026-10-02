<?php

namespace App\Services;

/**
 * Contrato del almacen de usuarios.
 *
 * Existe para que la aplicacion dependa de una abstraccion y no de una
 * implementacion concreta, y para que los tests puedan ejercitar exactamente
 * la misma implementacion que corre en produccion.
 *
 * Todas las implementaciones trabajan con arrays asociativos con las claves:
 * id, name, username, role, is_active, profile_image_path, profile_frame_color,
 * created_at, last_login_at, last_seen_at, password_hash y los campos two_factor_*.
 */
interface UserStore
{
    public function bootstrapAdminFromEnv(bool $createIfMissing = false): void;

    public function hasAdminAccount(): bool;

    public function createOrUpdateAdmin(string $username, string $password): array;

    /** @return array<int, array<string, mixed>> */
    public function allPublicUsers(): array;

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array;

    /** @return array<string, mixed>|null */
    public function findByLoginIdentifier(string $identifier): ?array;

    /** @return array<string, mixed>|null */
    public function findPublicProfile(string $username): ?array;

    /** @return array<string, mixed>|null */
    public function verifyCredentials(string $username, string $password): ?array;

    public function touchPresence(string $username): void;

    public function createUser(string $username, string $password, string $role = 'user', ?string $name = null): array;

    /** @return array<int, array<string, mixed>> */
    public function searchPublicUsers(string $query, int $limit = 30): array;

    public function updatePassword(string $username, string $newPassword): void;

    public function verifyPassword(string $username, string $password): bool;

    public function hasTwoFactorEnabled(string $username): bool;

    /** @param array<int, string> $recoveryCodeHashes */
    public function enableTwoFactor(string $username, string $encryptedSecret, array $recoveryCodeHashes): void;

    public function disableTwoFactor(string $username): void;

    /** @param array<int, string> $recoveryCodeHashes */
    public function replaceTwoFactorRecoveryCodes(string $username, array $recoveryCodeHashes): void;

    public function twoFactorSecret(string $username): ?string;

    public function consumeTwoFactorRecoveryCode(string $username, string $code): bool;

    public function updateProfileAppearance(string $username, ?string $profileImagePath, ?string $profileFrameColor): void;

    public function recordLogin(string $username): void;

    public function generateRandomUsername(string $prefix = 'user'): string;

    public function generateRandomPassword(int $length = 12): string;

    public function deactivateUser(string $username): void;

    public function activateUser(string $username): void;

    public function deleteUser(string $username): void;

    public function countAdmins(): int;

    public function countActiveAdmins(): int;
}
