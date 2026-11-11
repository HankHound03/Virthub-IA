<?php

namespace App\Support;

/**
 * Estado de presencia de una persona, al estilo de Windows Live Messenger.
 *
 * Hay tres estados y se deducen de la ultima vez que dio senal de vida:
 *
 *   en linea     dio senal hace poco (la pestana esta abierta y activa)
 *   ausente      la sesion sigue, pero lleva un rato sin hacer nada
 *   desconectado cerro la pestana, salio de la cuenta o lleva mucho sin dar senal
 *
 * El navegador manda una senal cada minuto mientras la pestana esta visible; al
 * ocultarla deja de mandarla, y por eso el estado va cayendo solo.
 */
class ChatPresence
{
    /** Segundos sin senal para pasar de "en linea" a "ausente". */
    public const AWAY_AFTER = 300;

    /** Segundos sin senal para pasar de "ausente" a "desconectado". */
    public const OFFLINE_AFTER = 1800;

    /** A partir de aqui ya no se cuentan dias: "mucho tiempo". */
    public const MUCH_TIME_AFTER = 2419200; // 4 semanas

    public function isRecent(?string $lastSeenAt, ?int $windowSeconds = null): bool
    {
        return $this->secondsSince($lastSeenAt) <= ($windowSeconds ?? (int) config('virthub.chat.presence_window_seconds', 90));
    }

    /**
     * Devuelve "online", "away" u "offline".
     */
    public function state(?string $lastSeenAt): string
    {
        $segundos = $this->secondsSince($lastSeenAt);

        if ($segundos === null) {
            return 'offline';
        }

        if ($segundos <= (int) config('virthub.chat.away_after_seconds', self::AWAY_AFTER)) {
            return 'online';
        }

        if ($segundos <= (int) config('virthub.chat.offline_after_seconds', self::OFFLINE_AFTER)) {
            return 'away';
        }

        return 'offline';
    }

    /**
     * Texto para mostrar en el chat: "En linea", "Ausente hace 12 min",
     * "Desconectado hace 3 dias" o "Hace mucho tiempo".
     */
    public function describe(?string $lastSeenAt): string
    {
        $segundos = $this->secondsSince($lastSeenAt);

        if ($segundos === null) {
            return 'Sin conexión';
        }

        $estado = $this->state($lastSeenAt);

        if ($estado === 'online') {
            return 'En línea';
        }

        $transcurrido = $this->humanInterval($segundos);

        return $estado === 'away'
            ? 'Ausente hace ' . $transcurrido
            : 'Desconectado hace ' . $transcurrido;
    }

    /**
     * "12 min", "3 horas", "2 dias", "3 semanas" o "mucho tiempo".
     */
    public function humanInterval(?int $segundos): string
    {
        if ($segundos === null) {
            return 'mucho tiempo';
        }

        if ($segundos >= (int) config('virthub.chat.much_time_after_seconds', self::MUCH_TIME_AFTER)) {
            return 'mucho tiempo';
        }

        if ($segundos < 60) {
            return 'un momento';
        }

        if ($segundos < 3600) {
            $minutos = (int) floor($segundos / 60);

            return $minutos . ($minutos === 1 ? ' minuto' : ' minutos');
        }

        if ($segundos < 86400) {
            $horas = (int) floor($segundos / 3600);

            return $horas . ($horas === 1 ? ' hora' : ' horas');
        }

        if ($segundos < 604800) {
            $dias = (int) floor($segundos / 86400);

            return $dias . ($dias === 1 ? ' día' : ' días');
        }

        $semanas = (int) floor($segundos / 604800);

        return $semanas . ($semanas === 1 ? ' semana' : ' semanas');
    }

    /**
     * Segundos desde la marca, o null si no hay marca valida.
     */
    private function secondsSince(?string $lastSeenAt): ?int
    {
        if ($lastSeenAt === null || $lastSeenAt === '') {
            return null;
        }

        $timestamp = strtotime($lastSeenAt);

        if ($timestamp === false) {
            return null;
        }

        return max(0, time() - $timestamp);
    }
}
