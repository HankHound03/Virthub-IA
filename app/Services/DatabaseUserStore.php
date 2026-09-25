<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseUserStore extends JsonUserStore
{
    public function bootstrapAdminFromEnv(bool $createIfMissing = false): void
    {
        if (!$createIfMissing) {
            return;
        }

        $username = (string) env('ADMIN_USERNAME', 'admin');
        if (!User::where('username', $username)->exists()) {
            $this->createUser($username, (string) env('ADMIN_PASSWORD', 'ChangeMeNow123!'), 'admin');
        }
    }

    public function hasAdminAccount(): bool
    {
        return User::where('role', 'admin')->exists();
    }

    public function createOrUpdateAdmin(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new RuntimeException('El username y la contraseña de administrador son obligatorios.');
        }

        $user = User::where('username', $username)->first();
        if (!$user) {
            $this->createUser($username, $password, 'admin');
        } else {
            $user->forceFill(['password' => $password, 'role' => 'admin', 'is_active' => true])->save();
        }

        return ['username' => $username, 'role' => 'admin'];
    }

    public function allPublicUsers(): array
    {
        return User::query()->orderBy('id')->get()->map(fn (User $user): array => $this->publicUser($user))->all();
    }

    public function findByUsername(string $username): ?array
    {
        $user = User::where('username', trim($username))->first();
        return $user ? $this->legacyUser($user) : null;
    }

    public function findByLoginIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        $user = User::where('username', $identifier)->orWhere('id', $identifier)->first();
        if (!$user) {
            $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($identifier)])->first();
        }

        return $user ? $this->legacyUser($user) : null;
    }

    public function findPublicProfile(string $username): ?array
    {
        $user = User::where('username', trim($username))->where('is_active', true)->first();
        if (!$user) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'username' => (string) $user->username,
            'role' => (string) $user->role,
            'profile_image_path' => $user->profile_image_path,
            'profile_frame_color' => $user->profile_frame_color,
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }

    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = User::where(function ($query) use ($username): void {
            $query->where('username', trim($username))->orWhere('id', trim($username));
        })->first();

        if (!$user || !$user->is_active || !Hash::check($password, (string) $user->password)) {
            return null;
        }

        return ['username' => $user->username, 'role' => $user->role];
    }

    public function touchPresence(string $username): void
    {
        $this->requireUser($username)->update(['last_seen_at' => now()]);
    }

    public function createUser(string $username, string $password, string $role = 'user', ?string $name = null): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new RuntimeException('Username y password son obligatorios.');
        }
        if (User::where('username', $username)->exists()) {
            throw new RuntimeException('Ese username ya existe.');
        }

        $user = User::create([
            'name' => trim((string) ($name ?: $username)),
            'username' => $username,
            'email' => $this->emailFor($username),
            'password' => $password,
            'role' => $role === 'admin' ? 'admin' : 'user',
            'is_active' => true,
            'profile_frame_color' => '#6ea8ff',
        ]);

        return ['id' => (string) $user->id, 'name' => $user->name, 'username' => $user->username, 'role' => $user->role];
    }

    public function searchPublicUsers(string $query, int $limit = 30): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return User::query()->where(function ($builder) use ($query): void {
            $builder->where('username', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->orWhere('id', 'like', "%{$query}%");
        })->limit(max(1, $limit))->get()->map(fn (User $user): array => $this->publicUser($user))->all();
    }

    public function updatePassword(string $username, string $newPassword): void
    {
        $this->requireUser($username)->update(['password' => $newPassword]);
    }

    public function verifyPassword(string $username, string $password): bool
    {
        $user = User::where('username', trim($username))->first();
        return $user !== null && Hash::check($password, (string) $user->password);
    }

    public function hasTwoFactorEnabled(string $username): bool
    {
        $user = User::where('username', trim($username))->first();
        return $user !== null && $user->two_factor_enabled && trim((string) $user->two_factor_secret) !== '';
    }

    public function enableTwoFactor(string $username, string $encryptedSecret, array $recoveryCodeHashes): void
    {
        $this->requireUser($username)->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => $encryptedSecret,
            'two_factor_recovery_codes' => array_values($recoveryCodeHashes),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function disableTwoFactor(string $username): void
    {
        $this->requireUser($username)->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function replaceTwoFactorRecoveryCodes(string $username, array $recoveryCodeHashes): void
    {
        $this->requireUser($username)->update(['two_factor_recovery_codes' => array_values($recoveryCodeHashes)]);
    }

    public function twoFactorSecret(string $username): ?string
    {
        $user = User::where('username', trim($username))->first();
        return $user && $this->hasTwoFactorEnabled($username) ? (string) $user->two_factor_secret : null;
    }

    public function consumeTwoFactorRecoveryCode(string $username, string $code): bool
    {
        $user = $this->requireUser($username);
        $codes = is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : [];

        foreach ($codes as $index => $hash) {
            if (Hash::check(strtoupper(trim($code)), (string) $hash)) {
                unset($codes[$index]);
                $user->update(['two_factor_recovery_codes' => array_values($codes)]);
                return true;
            }
        }

        return false;
    }

    public function updateProfileAppearance(string $username, ?string $profileImagePath, ?string $profileFrameColor): void
    {
        $attributes = [];
        if ($profileImagePath !== null) {
            $attributes['profile_image_path'] = $profileImagePath;
        }
        if ($profileFrameColor !== null) {
            $attributes['profile_frame_color'] = $profileFrameColor;
        }
        $this->requireUser($username)->update($attributes);
    }

    public function recordLogin(string $username): void
    {
        $this->requireUser($username)->update(['last_login_at' => now(), 'last_seen_at' => now()]);
    }

    public function generateRandomUsername(string $prefix = 'user'): string
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix) ?: 'user';
        do {
            $candidate = strtolower($safePrefix) . '_' . strtolower(Str::random(6));
        } while (User::where('username', $candidate)->exists());
        return $candidate;
    }

    public function generateRandomPassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $digits = '0123456789';
        $symbols = '!@#$%^&*';
        $all = $uppercase . $lowercase . $digits . $symbols;
        $password = $uppercase[random_int(0, strlen($uppercase) - 1)]
            . $lowercase[random_int(0, strlen($lowercase) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)]
            . $symbols[random_int(0, strlen($symbols) - 1)];
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($password);
    }

    public function deactivateUser(string $username): void
    {
        $user = $this->requireUser($username);
        if (strtolower($user->username) === 'admin') {
            throw new RuntimeException('No se permite desactivar la cuenta admin principal.');
        }
        if ($user->role === 'admin' && $this->countActiveAdmins() <= 1) {
            throw new RuntimeException('No puedes desactivar al ultimo admin activo.');
        }
        $user->update(['is_active' => false]);
    }

    public function activateUser(string $username): void
    {
        $this->requireUser($username)->update(['is_active' => true]);
    }

    public function deleteUser(string $username): void
    {
        $user = $this->requireUser($username);
        if (strtolower($user->username) === 'admin') {
            throw new RuntimeException('No se permite eliminar la cuenta admin principal.');
        }
        $user->delete();
    }

    public function countAdmins(): int
    {
        return User::where('role', 'admin')->count();
    }

    public function countActiveAdmins(): int
    {
        return User::where('role', 'admin')->where('is_active', true)->count();
    }

    public function importLegacyJson(?string $path = null): int
    {
        $path = $path ?: storage_path('app/data/users.json');
        if (!is_file($path)) {
            return 0;
        }

        $records = json_decode((string) file_get_contents($path), true);
        if (!is_array($records)) {
            throw new RuntimeException('El archivo users.json no contiene datos validos.');
        }

        $imported = 0;
        foreach ($records as $record) {
            $username = trim((string) ($record['username'] ?? ''));
            if ($username === '' || empty($record['password_hash'])) {
                continue;
            }

            $user = User::firstOrNew(['username' => $username]);
            $user->forceFill([
                'name' => (string) ($record['name'] ?? $username),
                'email' => $user->email ?: $this->emailFor($username),
                'password' => (string) $record['password_hash'],
                'role' => ($record['role'] ?? 'user') === 'admin' ? 'admin' : 'user',
                'is_active' => (bool) ($record['is_active'] ?? true),
                'profile_image_path' => $record['profile_image_path'] ?? null,
                'profile_frame_color' => $record['profile_frame_color'] ?? '#6ea8ff',
                'last_login_at' => $record['last_login_at'] ?? null,
                'last_seen_at' => $record['last_seen_at'] ?? null,
                'two_factor_enabled' => (bool) ($record['two_factor_enabled'] ?? false),
                'two_factor_secret' => $record['two_factor_secret'] ?? null,
                'two_factor_recovery_codes' => $record['two_factor_recovery_codes'] ?? [],
                'two_factor_confirmed_at' => $record['two_factor_confirmed_at'] ?? null,
            ])->save();
            $imported++;
        }

        return $imported;
    }

    private function requireUser(string $username): User
    {
        $user = User::where('username', trim($username))->first();
        if (!$user) {
            throw new RuntimeException('No existe un usuario con ese username.');
        }
        return $user;
    }

    private function emailFor(string $username): string
    {
        return strtolower($username) . '@users.virthub.local';
    }

    private function publicUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'username' => (string) $user->username,
            'role' => (string) $user->role,
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
            'last_seen_at' => $user->last_seen_at?->toDateTimeString(),
            'is_active' => (bool) $user->is_active,
            'profile_image_path' => $user->profile_image_path,
            'profile_frame_color' => $user->profile_frame_color,
        ];
    }

    private function legacyUser(User $user): array
    {
        return array_merge($this->publicUser($user), [
            'password_hash' => $user->password,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
            'two_factor_secret' => $user->two_factor_secret,
            'two_factor_recovery_codes' => $user->two_factor_recovery_codes ?? [],
            'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateTimeString(),
        ]);
    }
}
