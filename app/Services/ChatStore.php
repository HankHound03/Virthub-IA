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
        return $this->updateData(function (array &$data) use ($fromUser, $toUser, $message): array {
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

            return $entry;
        });
    }

    /**
     * Borra una conversacion completa.
     *
     * Se usa en el chat de IA: al empezar de cero, el modelo deja de arrastrar
     * respuestas anteriores (y cualquier personalidad que se hubiera colado en el
     * historial).
     */
    public function borrarConversacion(string $firstUser, string $secondUser): void
    {
        $this->updateData(function (array &$data) use ($firstUser, $secondUser): void {
            $key = $this->conversationKey($firstUser, $secondUser);

            unset($data['conversations'][$key]);
        });
    }

    /**
     * Apunta que un usuario esta escribiendo en una conversacion.
     *
     * No se guarda un "si/no" sino la hora del ultimo aviso: asi el otro lado
     * puede considerar que sigue escribiendo solo si el aviso es reciente, y no
     * se queda el cartel pegado si la persona cierra el navegador.
     */
    public function marcarEscribiendo(string $usuario, string $otro): void
    {
        $this->updateData(function (array &$data) use ($usuario, $otro): void {
            $key = $this->conversationKey($usuario, $otro);

            $data['escribiendo'] = is_array($data['escribiendo'] ?? null) ? $data['escribiendo'] : [];
            $data['escribiendo'][$key][$this->normalizeUser($usuario)] = time();
        });
    }

    /**
     * Dice si el OTRO esta escribiendo en esta conversacion.
     */
    public function estaEscribiendo(string $usuario, string $otro, int $segundos = 6): bool
    {
        $data = $this->readData();
        $key = $this->conversationKey($usuario, $otro);

        $aviso = $data['escribiendo'][$key][$this->normalizeUser($otro)] ?? 0;

        return is_int($aviso) && (time() - $aviso) <= $segundos;
    }

    /**
     * Marca como leidos los mensajes que el otro me envio.
     *
     * Devuelve cuantos cambio, para no reescribir el archivo si no hay nada nuevo.
     */
    public function marcarLeidos(string $usuario, string $otro): int
    {
        $usuario = $this->normalizeUser($usuario);
        $otro = $this->normalizeUser($otro);

        /*
         * Primero se mira si queda algo por marcar, sin abrir el archivo para
         * escribir. El chat se refresca cada pocos segundos por cada contacto:
         * reescribir el JSON cuando no hay nada nuevo seria un gasto inutil.
         */
        $key = $this->conversationKey($usuario, $otro);

        $pendientes = 0;

        foreach ($this->readData()['conversations'][$key] ?? [] as $entrada) {
            if (($entrada['to'] ?? '') === $usuario
                && ($entrada['from'] ?? '') === $otro
                && empty($entrada['read_at'])) {
                $pendientes++;
            }
        }

        if ($pendientes === 0) {
            return 0;
        }

        return $this->updateData(function (array &$data) use ($usuario, $otro, $key): int {
            $marcados = 0;

            foreach ($data['conversations'][$key] ?? [] as $indice => $entrada) {
                $esParaMi = ($entrada['to'] ?? '') === $usuario;
                $esDelOtro = ($entrada['from'] ?? '') === $otro;

                if ($esParaMi && $esDelOtro && empty($entrada['read_at'])) {
                    $data['conversations'][$key][$indice]['read_at'] = date('Y-m-d H:i:s');
                    $marcados++;
                }
            }

            return $marcados;
        });
    }

    /**
     * Mensajes sin leer por remitente.
     *
     * Devuelve ['usuario' => cuantos]. Se usa para el contador que aparece al
     * lado de cada persona en la lista de usuarios del chat.
     *
     * @return array<string, int>
     */
    public function conteoNoLeidos(string $usuario): array
    {
        $usuario = $this->normalizeUser($usuario);
        $conteos = [];

        foreach ($this->readData()['conversations'] ?? [] as $entradas) {
            foreach ($entradas as $entrada) {
                if (($entrada['to'] ?? '') !== $usuario || ! empty($entrada['read_at'])) {
                    continue;
                }

                $de = (string) ($entrada['from'] ?? '');

                if ($de === '' || $de === $usuario) {
                    continue;
                }

                $conteos[$de] = ($conteos[$de] ?? 0) + 1;
            }
        }

        return $conteos;
    }
    public function getBroadcastMessages(): array
    {
        $data = $this->readData();

        return array_values($data['broadcasts'] ?? []);
    }

    public function appendBroadcastMessage(string $fromUser, string $message): array
    {
        return $this->updateData(function (array &$data) use ($fromUser, $message): array {
            $entry = [
                'from' => $fromUser,
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $data['broadcasts'] = $data['broadcasts'] ?? [];
            $data['broadcasts'][] = $entry;
            $data['broadcasts'] = array_slice($data['broadcasts'], -100);

            return $entry;
        });
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

    private function updateData(callable $callback): mixed
    {
        $this->ensureStore();

        $handle = fopen($this->filePath, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el chat JSON.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el chat JSON.');
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            $decoded = json_decode($content !== false ? $content : '', true);

            $data = is_array($decoded) ? $decoded : [
                'conversations' => [],
                'broadcasts' => [],
            ];

            $data['conversations'] = is_array($data['conversations'] ?? null) ? $data['conversations'] : [];
            $data['broadcasts'] = is_array($data['broadcasts'] ?? null) ? $data['broadcasts'] : [];

            $result = $callback($data);

            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                throw new RuntimeException('No se pudo guardar el chat JSON.');
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

}