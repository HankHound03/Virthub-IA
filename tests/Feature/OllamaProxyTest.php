<?php

namespace Tests\Feature;

use App\Services\JsonUserStore;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaProxyTest extends TestCase
{
    /**
     * Configura Ollama mediante config(), que es lo que lee OllamaClient. Los
     * tests ya no dependen de env() y por tanto siguen siendo validos con la
     * configuracion cacheada en produccion.
     */
    private function configureOllama(string $baseUrl, string $model = 'llama3.1'): void
    {
        config([
            'virthub.ollama.base_url' => $baseUrl,
            'virthub.ollama.model' => $model,
            'virthub.ollama.system_prompt' => 'Responde en español, de forma clara, breve y útil.',
        ]);
    }

    /**
     * Crea la cuenta admin igual que lo hace el instalador. No se usa
     * bootstrapAdminFromEnv(): esa via exige una ADMIN_PASSWORD explicita y no
     * acepta credenciales por defecto.
     */
    private function createAdmin(string $username = 'admin'): void
    {
        app(JsonUserStore::class)->createOrUpdateAdmin($username, 'P@ssword123!');
    }

    public function test_ollama_proxy_returns_503_when_not_configured(): void
    {
        $this->configureOllama('');
        $this->createAdmin();

        $response = $this->withSession([
            'auth_user' => [
                'username' => 'admin',
                'role' => 'admin',
            ],
        ])->postJson('/ai/ollama', [
            'prompt' => 'Hola',
        ]);

        $response->assertStatus(503);
        $response->assertJsonFragment([
            'error' => 'Configura OLLAMA_BASE_URL en .env para habilitar la IA local.',
        ]);
    }

    public function test_ollama_proxy_denies_non_admin_users(): void
    {
        $this->configureOllama('http://ollama.test');

        $users = app(JsonUserStore::class);
        $this->createAdmin();
        $username = 'user_' . uniqid('', true);
        $users->createUser($username, 'Password123!', 'user');

        $response = $this->withSession([
            'auth_user' => [
                'username' => $username,
                'role' => 'user',
            ],
        ])->postJson('/ai/ollama', [
            'prompt' => 'Hola',
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'error' => 'Solo el admin puede usar esta IA.',
        ]);
    }

    public function test_ollama_proxy_forwards_prompt_to_ollama(): void
    {
        $this->configureOllama('http://ollama.test');

        $this->createAdmin();

        Http::fake([
            'http://ollama.test/api/generate' => Http::response([
                'response' => 'Respuesta desde Ollama',
            ], 200),
        ]);

        $response = $this->withSession([
            'auth_user' => [
                'username' => 'admin',
                'role' => 'admin',
            ],
        ])->postJson('/ai/ollama', [
            'prompt' => 'Resume este proyecto',
        ]);

        $response->assertOk();
        $response->assertJson([
            'response' => 'Respuesta desde Ollama',
            'model' => 'llama3.1',
        ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://ollama.test/api/generate'
                && ($request['prompt'] ?? null) === 'Resume este proyecto'
                && ($request['model'] ?? null) === 'llama3.1'
                && ($request['stream'] ?? null) === false;
        });
    }

    public function test_admin_ollama_chat_persists_history(): void
    {
        $this->configureOllama('http://ollama.test');

        $users = app(JsonUserStore::class);
        $this->createAdmin();

        Http::fake([
            'http://ollama.test/api/chat' => Http::response([
                'message' => [
                    'content' => 'Respuesta del chat de IA',
                ],
            ], 200),
        ]);

        $response = $this->withSession([
            'auth_user' => [
                'username' => 'admin',
                'role' => 'admin',
            ],
        ])->postJson('/chat/conversation/ollama', [
            'message' => 'Hola IA',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'model' => 'llama3.1',
        ]);

        $history = $this->withSession([
            'auth_user' => [
                'username' => 'admin',
                'role' => 'admin',
            ],
        ])->getJson('/chat/conversation/ollama');

        $history->assertOk();
        $messages = $history->json('messages');

        $this->assertTrue(collect($messages)->contains(fn (array $message): bool => ($message['from'] ?? '') === 'admin' && ($message['message'] ?? '') === 'Hola IA'));
        $this->assertTrue(collect($messages)->contains(fn (array $message): bool => ($message['from'] ?? '') === 'ollama' && ($message['message'] ?? '') === 'Respuesta del chat de IA'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://ollama.test/api/chat'
                && ($request['model'] ?? null) === 'llama3.1'
                && ($request['stream'] ?? null) === false
                && is_array($request['messages'] ?? null);
        });
    }
}

