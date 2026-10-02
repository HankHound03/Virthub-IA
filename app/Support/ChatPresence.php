<?php

namespace App\Support;

/**
 * Determina si un usuario sigue conectado segun su ultima marca de presencia.
 */
class ChatPresence
{
    public function isRecent(?string $lastSeenAt, ?int $windowSeconds = null): bool
    {
        if ($lastSeenAt === null || $lastSeenAt === '') {
            return false;
        }

        $timestamp = strtotime($lastSeenAt);

        if ($timestamp === false) {
            return false;
        }

        $window = $windowSeconds ?? (int) config('virthub.chat.presence_window_seconds', 90);

        return (time() - $timestamp) <= $window;
    }
}
