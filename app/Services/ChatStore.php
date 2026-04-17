<?php

namespace App\Services;

use RuntimeException;

class ChatStore
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/chat.json');
    }

    public function getConversationMessages(string $firstUser, string $secondUser): array
    {
        $data = $this->readData();
        $key = $this->conversationKey($firstUser, $secondUser);

        return array_values($data['conversations'][$key] ?? []);
    }

    public function appendConversationMessage(string $fromUser, string $toUser, string $message): array
    {
        $data = $this->readData();
        $key = $this->conversationKey($fromUser, $toUser);

        $entry = [
            'from' => $fromUser,
            'to' => $toUser,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $data['conversations'][$key] = $data['conversations'][$key] ?? [];
        $data['conversations'][$key][] = $entry;
        $data['conversations'][$key] = array_slice($data['conversations'][$key], -100);

        $this->writeData($data);

        return $entry;
    }

    public function getBroadcastMessages(): array
    {
        $data = $this->readData();

        return array_values($data['broadcasts'] ?? []);
    }

    public function appendBroadcastMessage(string $fromUser, string $message): array
    {
        $data = $this->readData();

        $entry = [
            'from' => $fromUser,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $data['broadcasts'] = $data['broadcasts'] ?? [];
        $data['broadcasts'][] = $entry;
        $data['broadcasts'] = array_slice($data['broadcasts'], -100);

        $this->writeData($data);

        return $entry;
    }

    private function conversationKey(string $firstUser, string $secondUser): string
    {
        $participants = [$this->normalizeUser($firstUser), $this->normalizeUser($secondUser)];
        sort($participants, SORT_STRING);

        return implode('|', $participants);
    }

    private function normalizeUser(string $username): string
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('El username no puede estar vacio.');
        }

        return $username;
    }

    private function ensureStore(): void
    {
        $dirPath = dirname($this->filePath);

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([
                'conversations' => new \stdClass(),
                'broadcasts' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function readData(): array
    {
        $this->ensureStore();

        $content = file_get_contents($this->filePath);

        if ($content === false || trim($content) === '') {
            return [
                'conversations' => [],
                'broadcasts' => [],
            ];
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return [
                'conversations' => [],
                'broadcasts' => [],
            ];
        }

        $decoded['conversations'] = is_array($decoded['conversations'] ?? null) ? $decoded['conversations'] : [];
        $decoded['broadcasts'] = is_array($decoded['broadcasts'] ?? null) ? $decoded['broadcasts'] : [];

        return $decoded;
    }

    private function writeData(array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new RuntimeException('No se pudo guardar el chat JSON.');
        }

        file_put_contents($this->filePath, $encoded, LOCK_EX);
    }
}