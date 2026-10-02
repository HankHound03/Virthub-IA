<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP para Ollama, la IA local opcional del proyecto.
 *
 * Toda la configuracion se lee de config('virthub.ollama.*') para que sobreviva
 * a php artisan config:cache.
 */
class OllamaClient
{
    public function isEnabled(): bool
    {
        return $this->baseUrl() !== '';
    }

    public function model(): string
    {
        $model = trim((string) config('virthub.ollama.model', 'llama3.1'));

        return $model !== '' ? $model : 'llama3.1';
    }

    public function chatUsername(): string
    {
        return (string) config('virthub.ollama.chat_username', 'ollama');
    }

    public function isChatUsername(string $username): bool
    {
        return strtolower(trim($username)) === strtolower($this->chatUsername());
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): Response
    {
        return Http::connectTimeout($this->connectTimeout())
            ->timeout($this->requestTimeout())
            ->acceptJson()
            ->post($this->baseUrl() . '/api/chat', [
                'model' => $this->model(),
                'messages' => $messages,
                'stream' => false,
            ]);
    }

    public function generate(string $prompt): Response
    {
        return Http::connectTimeout($this->connectTimeout())
            ->timeout($this->requestTimeout())
            ->acceptJson()
            ->post($this->baseUrl() . '/api/generate', [
                'model' => $this->model(),
                'prompt' => $prompt,
                'system' => $this->systemPrompt(),
                'stream' => false,
            ]);
    }

    public function systemPrompt(): string
    {
        $prompt = trim((string) config('virthub.ollama.system_prompt', ''));

        return $prompt !== '' ? $prompt : 'Responde en español, de forma clara, breve y útil.';
    }

    private function baseUrl(): string
    {
        return rtrim(trim((string) config('virthub.ollama.base_url', '')), '/');
    }

    private function requestTimeout(): int
    {
        $timeout = (int) config('virthub.ollama.request_timeout', 300);

        return $timeout > 0 ? $timeout : 300;
    }

    private function connectTimeout(): int
    {
        $timeout = (int) config('virthub.ollama.connect_timeout', 5);

        return $timeout > 0 ? $timeout : 5;
    }
}
