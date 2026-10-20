<?php

namespace App\Http\Controllers;

use App\Support\ActiveUser;
use App\Support\GuestWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Limpieza del escritorio compartido de invitados.
 */
class GuestWorkspaceController extends Controller
{
    public function __construct(private readonly GuestWorkspace $espacio) {}

    /**
     * Borra la informacion personal que dejo el invitado.
     *
     * Se llama desde la pagina del escritorio cuando el contador llega a cero, y
     * tambien al entrar como invitado. Solo actua para invitados: un usuario
     * registrado no debe poder borrar el escritorio compartido.
     */
    public function limpiar(Request $request): JsonResponse
    {
        $currentUser = ActiveUser::from($request);

        if (! ActiveUser::isGuest($currentUser)) {
            return response()->json(['error' => 'Solo para invitados.'], 403);
        }

        if (! $this->espacio->disponible()) {
            // Sin montaje no se puede limpiar; se responde bien para no romper la
            // pagina, pero se deja constancia en el registro.
            logger()->warning('Escritorio de invitado no montado: no se pudo limpiar.');

            return response()->json(['limpiado' => false, 'motivo' => 'no disponible']);
        }

        $tocadas = $this->espacio->limpiar();

        return response()->json([
            'limpiado' => true,
            'rutas' => $tocadas,
        ]);
    }
}
