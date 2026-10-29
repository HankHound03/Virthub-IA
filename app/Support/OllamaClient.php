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

    /**
     * Modelos cargados ahora mismo en memoria (y cuanto ocupan en la GPU).
     */
    public function modelosCargados(): Response
    {
        return Http::connectTimeout(1)
            ->timeout(2)
            ->acceptJson()
            ->get($this->baseUrl() . '/api/ps');
    }

    /**
     * Personalidad del asistente.
     *
     * El orden es: lo que diga la variable de entorno, luego el archivo
     * resources/prompts/tutorutt.txt (asi el texto largo vive en su sitio y se
     * edita sin tocar el compose), y por ultimo una instruccion minima.
     */
    public function systemPrompt(): string
    {
        /*
         * Manda el archivo del proyecto.
         *
         * Antes ganaba OLLAMA_SYSTEM_PROMPT del .env, que era un texto corto y
         * tapaba la personalidad de verdad. El prompt del tutor es parte del
         * codigo: se versiona, se revisa y no depende de cada entorno.
         *
         * La variable queda como ultimo recurso, para poder cambiarlo sin
         * recompilar si algun dia hace falta.
         */
        $archivo = resource_path('prompts/tutorutt.txt');

        if (is_file($archivo)) {
            $leido = trim((string) @file_get_contents($archivo));

            if ($leido !== '') {
                return $leido;
            }
        }

        $prompt = trim((string) config('virthub.ollama.system_prompt', ''));

        if ($prompt !== '') {
            return $prompt;
        }

        return 'Responde en español, de forma clara, breve y útil.';
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
