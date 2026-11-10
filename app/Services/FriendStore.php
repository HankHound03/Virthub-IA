<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class FriendStore
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/friendships.json');
    }

    public function sendRequest(string $fromUsername, string $toUsername): array
    {
        $fromUsername = trim($fromUsername);
        $toUsername = trim($toUsername);

        if ($fromUsername === '' || $toUsername === '' || $fromUsername === $toUsername) {
            throw new RuntimeException('No puedes enviarte una solicitud a ti mismo.');
        }

        return $this->updateData(function (array &$data) use ($fromUsername, $toUsername): array {
            foreach ($data['requests'] as $request) {
                $samePair = ($request['from'] ?? '') === $fromUsername && ($request['to'] ?? '') === $toUsername;
                $reversePair = ($request['from'] ?? '') === $toUsername && ($request['to'] ?? '') === $fromUsername;

                if (($samePair || $reversePair) && ($request['status'] ?? '') === 'accepted') {
                    throw new RuntimeException('Ya son amigos.');
                }

                if ($samePair && ($request['status'] ?? '') === 'pending') {
                    throw new RuntimeException('Ya enviaste una solicitud a este usuario.');
                }

                if ($reversePair && ($request['status'] ?? '') === 'pending') {
                    throw new RuntimeException('Este usuario ya te envio una solicitud.');
                }
            }

            $record = [
                'id' => Str::uuid()->toString(),
                'from' => $fromUsername,
                'to' => $toUsername,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'responded_at' => null,
            ];

            $data['requests'][] = $record;
            $data['requests'] = array_slice($data['requests'], -5000);

            return $record;
        });
    }

    public function pendingFor(string $username): array
    {
        $username = trim($username);

        return array_values(array_filter($this->readData()['requests'], static function (array $request) use ($username): bool {
            return ($request['to'] ?? '') === $username && ($request['status'] ?? '') === 'pending';
        }));
    }

    public function statusBetween(string $firstUsername, string $secondUsername): ?array
    {
        foreach ($this->readData()['requests'] as $request) {
            $samePair = ($request['from'] ?? '') === $firstUsername && ($request['to'] ?? '') === $secondUsername;
            $reversePair = ($request['from'] ?? '') === $secondUsername && ($request['to'] ?? '') === $firstUsername;

            if ($samePair || $reversePair) {
                return $request;
            }
        }

        return null;
    }

    public function respondToRequest(string $requestId, string $username, string $status): array
    {
        $requestId = trim($requestId);
        $username = trim($username);
        $status = trim($status);

        if (!in_array($status, ['accepted', 'declined'], true)) {
            throw new RuntimeException('Respuesta de solicitud invalida.');
        }

        return $this->updateData(function (array &$data) use ($requestId, $username, $status): array {
            foreach ($data['requests'] as &$request) {
                if (($request['id'] ?? '') !== $requestId || ($request['to'] ?? '') !== $username) {
                    continue;
                }

                if (($request['status'] ?? '') !== 'pending') {
                    throw new RuntimeException('Esta solicitud ya fue respondida.');
                }

                $request['status'] = $status;
                $request['responded_at'] = date('Y-m-d H:i:s');

                return $request;
            }
            unset($request);

            throw new RuntimeException('No existe esa solicitud de amistad.');
        });
    }

    /**
     * Elimina la amistad entre dos personas.
     *
     * Se borra la solicitud aceptada que las une, no la cuenta ni el chat: la
     * conversacion se queda, solo deja de aparecer en la lista de amigos.
     */
    public function removeFriend(string $firstUsername, string $secondUsername): bool
    {
        $firstUsername = trim($firstUsername);
        $secondUsername = trim($secondUsername);

        if ($firstUsername === '' || $secondUsername === '' || $firstUsername === $secondUsername) {
            return false;
        }

        return $this->updateData(function (array &$data) use ($firstUsername, $secondUsername): bool {
            foreach ($data['requests'] as $indice => $request) {
                $from = (string) ($request['from'] ?? "");
                $to = (string) ($request['to'] ?? "");
                $estado = (string) ($request['status'] ?? "");

                $sonEstos = ($from === $firstUsername && $to === $secondUsername)
                    || ($from === $secondUsername && $to === $firstUsername);

                if (! $sonEstos || $estado !== "accepted") {
                    continue;
                }

                unset($data["requests"][$indice]);
                $data["requests"] = array_values($data["requests"]);

                return true;
            }

            return false;
        });
    }

    public function friendsOf(string $username): array
    {
        $username = trim($username);
        $friends = [];

        foreach ($this->readData()['requests'] as $request) {
            if (($request['status'] ?? '') !== 'accepted') {
                continue;
            }

            if (($request['from'] ?? '') === $username) {
                $friends[] = (string) ($request['to'] ?? '');
            } elseif (($request['to'] ?? '') === $username) {
                $friends[] = (string) ($request['from'] ?? '');
            }
        }

        return array_values(array_unique(array_filter($friends)));
    }

    private function readData(): array
    {
        $this->ensureStore();
        $content = file_get_contents($this->filePath);
        $decoded = json_decode($content !== false ? $content : '', true);

        return [
            'requests' => is_array($decoded['requests'] ?? null) ? $decoded['requests'] : [],
        ];
    }

    private function updateData(callable $callback): mixed
    {
        $this->ensureStore();
        $handle = fopen($this->filePath, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el registro de amistades.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el registro de amistades.');
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            $decoded = json_decode($content !== false ? $content : '', true);
            $data = ['requests' => is_array($decoded['requests'] ?? null) ? $decoded['requests'] : []];
            $result = $callback($data);
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                throw new RuntimeException('No se pudo guardar el registro de amistades.');
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

    private function ensureStore(): void
    {
        $directory = dirname($this->filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode(['requests' => []], JSON_PRETTY_PRINT));
        }
    }
}
