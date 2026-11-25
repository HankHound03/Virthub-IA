<?php

namespace App\Support;

use RuntimeException;

/**
 * El turno del invitado.
 *
 * Por ahora el escritorio de invitados (ct7) es uno solo y compartido, asi que
 * solo puede haber un invitado dentro a la vez. Este almacen guarda quien lo
 * tiene y hasta cuando.
 *
 * Sin esto, dos personas podian entrar como invitados a la vez y la entrada del
 * segundo borraba los archivos del primero (GuestWorkspace limpia al entrar).
 *
 * El turno caduca solo: si el invitado cierra el navegador sin salir, el turno
 * queda libre en cuanto pasa su hora de expiracion, sin necesidad de limpieza.
 */
class GuestSlot
{
    private string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/data/guest-slot.json');
    }

    /**
     * Si el turno esta ocupado por otra sesion y todavia no ha caducado.
     */
    public function ocupadoPorOtro(string $sessionId): bool
    {
        $turno = $this->leer();

        if ($turno === null) {
            return false;
        }

        if (($turno['session'] ?? '') === $sessionId) {
            return false;
        }

        return (int) ($turno['expires_at'] ?? 0) > time();
    }

    /**
     * Cuantos minutos le quedan al invitado que lo tiene.
     */
    public function minutosRestantes(): int
    {
        $turno = $this->leer();

        if ($turno === null) {
            return 0;
        }

        return max(0, (int) ceil(((int) ($turno['expires_at'] ?? 0) - time()) / 60));
    }

    /**
     * Reserva el turno para esta sesion.
     */
    public function reclamar(string $sessionId, int $expiresAt): void
    {
        $this->escribir([
            'session' => $sessionId,
            'expires_at' => $expiresAt,
            'started_at' => date('c'),
        ]);
    }

    /**
     * Libera el turno, pero solo si es de esta sesion.
     */
    public function liberar(string $sessionId): void
    {
        $turno = $this->leer();

        if ($turno !== null && ($turno['session'] ?? '') !== $sessionId) {
            return;
        }

        @unlink($this->filePath);
    }

    /** @return array<string, mixed>|null */
    private function leer(): ?array
    {
        if (! is_file($this->filePath)) {
            return null;
        }

        $datos = json_decode((string) @file_get_contents($this->filePath), true);

        return is_array($datos) ? $datos : null;
    }

    /** @param array<string, mixed> $datos */
    private function escribir(array $datos): void
    {
        $carpeta = dirname($this->filePath);

        if (! is_dir($carpeta) && ! mkdir($carpeta, 0775, true) && ! is_dir($carpeta)) {
            throw new RuntimeException('No se pudo guardar el turno del invitado.');
        }

        $json = json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false || @file_put_contents($this->filePath, $json . "\n") === false) {
            throw new RuntimeException('No se pudo guardar el turno del invitado.');
        }
    }
}
