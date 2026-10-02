<?php

namespace App\Http\Controllers;

use App\Support\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Puente HTTP hacia Ollama para el panel de IA.
 */
class AiController extends Controller
{
    public function __construct(private readonly OllamaClient $ollama) {}

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:4000',
        ]);

        if (! $this->ollama->isEnabled()) {
            return response()->json([
                'error' => 'Configura OLLAMA_BASE_URL en .env para habilitar la IA local.',
            ], 503);
        }

        try {
            $response = $this->ollama->generate((string) $validated['prompt']);
        } catch (Throwable) {
            return response()->json(['error' => 'No se pudo conectar con Ollama.'], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Ollama devolvio un error.',
                'details' => $response->json() ?: $response->body(),
            ], 502);
        }

        $reply = trim((string) ($response->json('response') ?? ''));

        if ($reply === '') {
            return response()->json(['error' => 'Ollama no devolvio texto util.'], 502);
        }

        return response()->json([
            'response' => $reply,
            'model' => $this->ollama->model(),
        ]);
    }
}
