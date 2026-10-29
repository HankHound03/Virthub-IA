<?php

namespace App\Http\Controllers;

use App\Services\GpuMonitor;
use App\Support\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Puente HTTP hacia Ollama para el panel de IA.
 */
class AiController extends Controller
{
    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly GpuMonitor $gpu,
    ) {}

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

        /*
         * Tiempos que devuelve Ollama, para que el panel pueda ensenar a que
         * velocidad esta respondiendo el modelo (tokens por segundo).
         */
        $datos = (array) $response->json();
        $evaluados = (int) ($datos['eval_count'] ?? 0);
        $duracionEval = (int) ($datos['eval_duration'] ?? 0);

        $porSegundo = ($evaluados > 0 && $duracionEval > 0)
            ? round($evaluados / ($duracionEval / 1_000_000_000), 1)
            : null;

        return response()->json([
            'response' => $reply,
            'model' => $this->ollama->model(),
            'metrics' => [
                'tokens' => $evaluados,
                'segundos' => round(((int) ($datos['total_duration'] ?? 0)) / 1_000_000_000, 2),
                'por_segundo' => $porSegundo,
            ],
        ]);
    }

    /**
     * Estado de la GPU y del modelo, para el monitor del panel de IA.
     *
     * Todo es opcional: si el puente de la GPU no esta levantado o Ollama no
     * responde, se devuelve lo que se haya podido leer y el panel se apaña.
     */
    public function recursos(): JsonResponse
    {
        $gpu = $this->gpu->leer();
        $modelos = [];
        $ollamaOk = false;

        try {
            $respuesta = $this->ollama->modelosCargados();

            if ($respuesta->successful()) {
                $ollamaOk = true;

                foreach ((array) $respuesta->json('models', []) as $modelo) {
                    $modelos[] = [
                        'name' => (string) ($modelo['name'] ?? ''),
                        'vram' => (int) ($modelo['size'] ?? 0),
                    ];
                }
            }
        } catch (Throwable) {
            $ollamaOk = false;
        }

        return response()->json([
            'monitor' => $this->gpu->habilitado(),
            'gpu' => $gpu,
            'ollama' => [
                'ok' => $ollamaOk,
                'modelo' => $this->ollama->model(),
                'cargados' => $modelos,
            ],
        ]);
    }
}
