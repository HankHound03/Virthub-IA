<?php

namespace App\Http\Controllers;

use App\Support\ActiveUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Buzon de sugerencias anonimas o identificadas.
 */
class SuggestionController extends Controller
{
    /**
     * Tope del buzon. Sin el, el archivo crece con cada envio (el POST es
     * publico) y el panel del administrador lo lee y ordena entero cada vez.
     */
    private const MAX_SUGERENCIAS = 500;

    public function index(Request $request): View
    {
        return view('sugerencias', [
            'currentUser' => ActiveUser::from($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentUser = ActiveUser::from($request);
        $canIdentify = ActiveUser::isRegistered($currentUser);

        $validated = $request->validate([
            'author_mode' => 'required|string|in:anonymous,identified',
            'message' => 'required|string|min:8|max:2000',
        ]);

        $authorMode = $canIdentify ? (string) $validated['author_mode'] : 'anonymous';
        $author = 'Anonimo';

        if ($authorMode === 'identified') {
            $author = ActiveUser::username($currentUser) ?: 'visitante';
        }

        try {
            // Si el buzon esta lleno, se avisa en vez de seguir creciendo.
            if ($this->cuantasSugerencias() >= self::MAX_SUGERENCIAS) {
                return back()->with('error', 'El buzon de sugerencias esta lleno. Avisa al administrador.');
            }

            $this->appendToStore([
                'id' => bin2hex(random_bytes(8)),
                'author_mode' => $authorMode,
                'author' => $author,
                'message' => trim((string) $validated['message']),
                'created_at' => date('c'),
            ]);
        } catch (RuntimeException) {
            return redirect('/sugerencias')->with('error', 'No se pudo registrar la sugerencia en este momento.');
        }

        return redirect('/sugerencias')->with('success', 'Gracias por compartir tu sugerencia. Ya fue registrada.');
    }

    /**
     * Cuantas sugerencias hay guardadas.
     */
    private function cuantasSugerencias(): int
    {
        $file = storage_path('app/data/suggestions.json');

        if (! is_file($file)) {
            return 0;
        }

        $decoded = json_decode((string) @file_get_contents($file), true);

        return is_array($decoded) && is_array($decoded['suggestions'] ?? null)
            ? count($decoded['suggestions'])
            : 0;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function appendToStore(array $entry): void
    {
        $dataDir = storage_path('app/data');

        if (! is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $file = $dataDir . DIRECTORY_SEPARATOR . 'suggestions.json';
        $handle = fopen($file, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo de sugerencias.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el archivo de sugerencias.');
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            $decoded = json_decode($content !== false ? $content : '', true);

            $payload = (is_array($decoded) && is_array($decoded['suggestions'] ?? null))
                ? $decoded
                : ['suggestions' => []];

            $payload['suggestions'][] = $entry;

            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($encoded === false) {
                throw new RuntimeException('No se pudo codificar la sugerencia.');
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
}
