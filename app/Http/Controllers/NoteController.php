<?php

namespace App\Http\Controllers;

use App\Services\NoteStore;
use App\Support\ActiveUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La libreta digital: notas en markdown, una libreta por persona.
 *
 * Sirve dos cosas:
 *   - la vista completa en /libreta
 *   - la misma vista dentro del flotante del contenedor (?modo=flotante)
 */
class NoteController extends Controller
{
    public function __construct(private readonly NoteStore $notes) {}

    public function index(Request $request): View
    {
        $usuario = ActiveUser::from($request);

        $nombre = ActiveUser::isRegistered($usuario)
            ? ActiveUser::username($usuario)
            : '';

        return view('libreta', [
            'currentUser' => $usuario,
            'currentPage' => 'libreta',
            'notas' => $nombre !== '' ? $this->notes->all($nombre) : [],
            'flotante' => $request->boolean('flotante'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario = $this->usuarioRegistrado($request);

        if ($usuario === null) {
            return response()->json(['error' => 'Inicia sesion para usar la libreta.'], 403);
        }

        $nota = $this->notes->create(
            $usuario,
            (string) $request->input('title', ''),
            (string) $request->input('body', '')
        );

        return response()->json(['note' => $nota]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $usuario = $this->usuarioRegistrado($request);

        if ($usuario === null) {
            return response()->json(['error' => 'Inicia sesion para usar la libreta.'], 403);
        }

        try {
            $nota = $this->notes->update(
                $usuario,
                $id,
                (string) $request->input('title', ''),
                (string) $request->input('body', '')
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['note' => $nota]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $usuario = $this->usuarioRegistrado($request);

        if ($usuario === null) {
            return response()->json(['error' => 'Inicia sesion para usar la libreta.'], 403);
        }

        try {
            $this->notes->delete($usuario, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['ok' => true]);
    }

    private function usuarioRegistrado(Request $request): ?string
    {
        $usuario = ActiveUser::from($request);

        if (! ActiveUser::isRegistered($usuario)) {
            return null;
        }

        return ActiveUser::username($usuario);
    }
}
