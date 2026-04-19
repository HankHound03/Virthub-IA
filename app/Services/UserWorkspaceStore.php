<?php

namespace App\Services;

use RuntimeException;

class UserWorkspaceStore
{
    private string $baseDir;

    public function __construct()
    {
        $this->baseDir = storage_path('app/data/workspace');
    }

    public function getState(string $username): array
    {
        $username = $this->normalizeUsername($username);
        $state = $this->readState($this->statePath($username));

        return $this->normalizeState(is_array($state) ? $state : []);
    }

    public function saveState(string $username, array $state): array
    {
        $username = $this->normalizeUsername($username);
        $normalized = $this->normalizeState($state);

        $this->writeState($this->statePath($username), $normalized);

        return $normalized;
    }

    private function normalizeUsername(string $username): string
    {
        $username = trim($username);

        if ($username === '') {
            throw new RuntimeException('Debes indicar un username valido.');
        }

        $safeUsername = preg_replace('/[^A-Za-z0-9_]/', '_', $username) ?: 'user';

        return $safeUsername;
    }

    private function statePath(string $username): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $username . '.json';
    }

    private function ensureStore(): void
    {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    private function readState(string $filePath): array
    {
        $this->ensureStore();

        if (!is_file($filePath)) {
            return [];
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }

            $content = stream_get_contents($handle);
            $decoded = json_decode($content !== false ? $content : '', true);

            return is_array($decoded) ? $decoded : [];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function writeState(string $filePath, array $state): void
    {
        $this->ensureStore();

        $handle = fopen($filePath, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el estado del usuario.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el estado del usuario.');
            }

            $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                throw new RuntimeException('No se pudo guardar el estado del usuario.');
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $encoded);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function normalizeState(array $state): array
    {
        $todos = [];

        foreach (array_slice(is_array($state['todos'] ?? null) ? $state['todos'] : [], 0, 120) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $text = trim(preg_replace('/\s+/', ' ', (string) ($item['text'] ?? '')) ?? '');

            if ($id === '' || $text === '') {
                continue;
            }

            $todos[] = [
                'id' => substr($id, 0, 80),
                'text' => mb_substr($text, 0, 120),
                'done' => (bool) ($item['done'] ?? false),
            ];
        }

        $notes = mb_substr(trim((string) ($state['notes'] ?? '')), 0, 2400);

        $calendarEvents = [];
        $rawCalendarEvents = is_array($state['calendarEvents'] ?? null) ? $state['calendarEvents'] : [];

        foreach ($rawCalendarEvents as $dateKey => $items) {
            $dateKey = (string) $dateKey;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateKey) || !is_array($items)) {
                continue;
            }

            $cleanedItems = [];
            foreach (array_slice($items, 0, 20) as $item) {
                $text = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $item) ?? ''), 0, 120);
                if ($text === '') {
                    continue;
                }

                $cleanedItems[] = $text;
            }

            if ($cleanedItems) {
                $calendarEvents[$dateKey] = $cleanedItems;
            }
        }

        return [
            'todos' => $todos,
            'notes' => $notes,
            'calendarEvents' => $calendarEvents,
            'updated_at' => date('c'),
        ];
    }
}