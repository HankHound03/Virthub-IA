<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class JsonUserStore
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/users.json');
    }

    public function bootstrapAdminFromEnv(): void
    {
        $adminUsername = (string) env('ADMIN_USERNAME', 'admin');
        $adminPassword = (string) env('ADMIN_PASSWORD', 'ChangeMeNow123!');

		$this->updateUsers(function (array &$users) use ($adminUsername, $adminPassword): void {
			foreach ($users as $user) {
				if (($user['role'] ?? '') === 'admin' && ($user['username'] ?? '') === $adminUsername) {
					return;
				}
			}

			$users[] = [
				'id' => Str::uuid()->toString(),
				'username' => $adminUsername,
				'password_hash' => Hash::make($adminPassword),
				'role' => 'admin',
				'is_active' => true,
				'profile_image_path' => null,
				'profile_frame_color' => '#6ea8ff',
				'created_at' => now()->toDateTimeString(),
				'last_login_at' => null,
				'last_seen_at' => null,
			];
		});
    }

    public function allPublicUsers(): array
    {
        $users = $this->readUsers();

        return array_map(function (array $user): array {
            return [
                'username' => $user['username'] ?? '',
                'role' => $user['role'] ?? 'user',
                'last_login_at' => $user['last_login_at'] ?? null,
                'last_seen_at' => $user['last_seen_at'] ?? null,
                'is_active' => $user['is_active'] ?? true,
                'profile_image_path' => $user['profile_image_path'] ?? null,
                'profile_frame_color' => $user['profile_frame_color'] ?? '#6ea8ff',
            ];
        }, $users);
    }

    public function findByUsername(string $username): ?array
    {
        $username = trim($username);

        foreach ($this->readUsers() as $user) {
            if (($user['username'] ?? '') === $username) {
                return $user;
            }
        }

        return null;
    }

    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);

        if (!$user) {
            return null;
        }

        if (!($user['is_active'] ?? true)) {
            return null;
        }

        if (!Hash::check($password, (string) ($user['password_hash'] ?? ''))) {
            return null;
        }

        return [
            'username' => $user['username'],
            'role' => $user['role'] ?? 'user',
        ];
    }

    public function touchPresence(string $username): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

        $this->updateUsers(function (array &$users) use ($username): void {
            $updated = false;
            $now = now()->toDateTimeString();

            foreach ($users as &$user) {
                if (($user['username'] ?? '') === $username) {
                    $user['last_seen_at'] = $now;
                    $updated = true;
                    break;
                }
            }
            unset($user);

            if (!$updated) {
                throw new RuntimeException('No existe un usuario con ese username.');
            }
        });
    }

    public function createUser(string $username, string $password, string $role = 'user'): array
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('El username no puede estar vacio.');
        }

        return $this->updateUsers(function (array &$users) use ($username, $password, $role): array {
            foreach ($users as $user) {
                if (($user['username'] ?? '') === $username) {
                    throw new RuntimeException('Ese username ya existe.');
                }
            }

            $record = [
                'id' => Str::uuid()->toString(),
                'username' => $username,
                'password_hash' => Hash::make($password),
                'role' => $role === 'admin' ? 'admin' : 'user',
                'is_active' => true,
                'profile_image_path' => null,
                'profile_frame_color' => '#6ea8ff',
                'created_at' => now()->toDateTimeString(),
                'last_login_at' => null,
                'last_seen_at' => null,
            ];

            $users[] = $record;

            return [
                'username' => $record['username'],
                'role' => $record['role'],
            ];
        });
    }

    public function updatePassword(string $username, string $newPassword): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

        $this->updateUsers(function (array &$users) use ($username, $newPassword): void {
            $updated = false;

            foreach ($users as &$user) {
                if (($user['username'] ?? '') === $username) {
                    $user['password_hash'] = Hash::make($newPassword);
                    $updated = true;
                    break;
                }
            }
            unset($user);

            if (!$updated) {
                throw new RuntimeException('No existe un usuario con ese username.');
            }
        });
    }

    public function verifyPassword(string $username, string $password): bool
    {
        $user = $this->findByUsername($username);

        if (!$user) {
            return false;
        }

        return Hash::check($password, (string) ($user['password_hash'] ?? ''));
    }

    public function updateProfileAppearance(string $username, ?string $profileImagePath, ?string $profileFrameColor): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

        $this->updateUsers(function (array &$users) use ($username, $profileImagePath, $profileFrameColor): void {
            $updated = false;

            foreach ($users as &$user) {
                if (($user['username'] ?? '') !== $username) {
                    continue;
                }

                if ($profileImagePath !== null) {
                    $user['profile_image_path'] = $profileImagePath;
                }

                if ($profileFrameColor !== null) {
                    $user['profile_frame_color'] = $profileFrameColor;
                }

                $updated = true;
                break;
            }
            unset($user);

            if (!$updated) {
                throw new RuntimeException('No existe un usuario con ese username.');
            }
        });
    }

    public function recordLogin(string $username): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

		$this->updateUsers(function (array &$users) use ($username): void {
			$updated = false;
			$loginAt = date('Y-m-d H:i:s');

			foreach ($users as &$user) {
				if (($user['username'] ?? '') === $username) {
					$user['last_login_at'] = $loginAt;
					$user['last_seen_at'] = $loginAt;
					$updated = true;
					break;
				}
			}
			unset($user);

			if (!$updated) {
				throw new RuntimeException('No existe un usuario con ese username.');
			}
		});
    }

    public function generateRandomUsername(string $prefix = 'user'): string
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix) ?: 'user';

        do {
            $candidate = strtolower($safePrefix) . '_' . strtolower(Str::random(6));
        } while ($this->findByUsername($candidate));

        return $candidate;
    }

    public function generateRandomPassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $digits = '0123456789';
        $symbols = '!@#$%^&*';
        $all = $uppercase . $lowercase . $digits . $symbols;

        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    public function deactivateUser(string $username): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

        $this->updateUsers(function (array &$users) use ($username): void {
            $updated = false;

            if ($this->countAdminsFromUsers($users, true) <= 1) {
                foreach ($users as $user) {
                    if (($user['username'] ?? '') === $username && ($user['role'] ?? 'user') === 'admin') {
                        throw new RuntimeException('No puedes desactivar al ultimo admin activo.');
                    }
                }
            }

            foreach ($users as &$user) {
                if (($user['username'] ?? '') === $username) {
                    $user['is_active'] = false;
                    $updated = true;
                    break;
                }
            }
            unset($user);

            if (!$updated) {
                throw new RuntimeException('No existe un usuario con ese username.');
            }
        });
    }

    public function activateUser(string $username): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

		$this->updateUsers(function (array &$users) use ($username): void {
			$updated = false;

			foreach ($users as &$user) {
				if (($user['username'] ?? '') === $username) {
					$user['is_active'] = true;
					$updated = true;
					break;
				}
			}
			unset($user);

			if (!$updated) {
				throw new RuntimeException('No existe un usuario con ese username.');
			}
		});
    }

    public function deleteUser(string $username): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

        $this->updateUsers(function (array &$users) use ($username): void {
            $targetUser = null;

            foreach ($users as $user) {
                if (($user['username'] ?? '') === $username) {
                    $targetUser = $user;
                    break;
                }
            }

            if (!$targetUser) {
                throw new RuntimeException('No existe un usuario con ese username.');
            }

            if (($targetUser['role'] ?? 'user') === 'admin' && $this->countAdminsFromUsers($users) <= 1) {
                throw new RuntimeException('No puedes eliminar al ultimo admin.');
            }

            $users = array_values(array_filter($users, function ($user) use ($username) {
                return ($user['username'] ?? '') !== $username;
            }));
        });
    }

    public function countAdmins(): int
    {
        return $this->countAdminsFromUsers($this->readUsers());
    }

    public function countActiveAdmins(): int
    {
        return $this->countAdminsFromUsers($this->readUsers(), true);
    }

    private function ensureStore(): void
    {
        $dirPath = dirname($this->filePath);

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function updateUsers(callable $callback): mixed
    {
        $this->ensureStore();

        $handle = fopen($this->filePath, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir la base de usuarios JSON.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear la base de usuarios JSON.');
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            $decoded = json_decode($content !== false ? $content : '', true);
            $users = is_array($decoded) ? $decoded : [];

            $result = $callback($users);

            $encoded = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                throw new RuntimeException('No se pudo guardar la base de usuarios JSON.');
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
            fflush($handle);

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function countAdminsFromUsers(array $users, bool $activeOnly = false): int
    {
        $count = 0;

        foreach ($users as $user) {
            if (($user['role'] ?? '') !== 'admin') {
                continue;
            }

            if ($activeOnly && !($user['is_active'] ?? true)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function readUsers(): array
    {
        $this->ensureStore();

        $content = file_get_contents($this->filePath);

        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeUsers(array $users): void
    {
        $encoded = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new RuntimeException('No se pudo guardar la base de usuarios JSON.');
        }

        file_put_contents($this->filePath, $encoded);
    }
}
