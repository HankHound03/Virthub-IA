<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ActiveUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cambio de contrasena obligatorio en el primer acceso.
 *
 * Se llega aqui cuando un administrador creo la cuenta con contrasena generada
 * al azar: en ese caso la conoce quien la creo, y el dueño debe poner una suya.
 */
class PasswordChangeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $currentUser = ActiveUser::from($request);

        if ($currentUser === null || ActiveUser::isGuest($currentUser)) {
            return redirect('/');
        }

        // Si ya no hace falta el cambio, no hay nada que hacer aqui.
        if (! $this->debeCambiar((string) $currentUser['username'])) {
            return redirect('/');
        }

        return view('cambiar-password', [
            'currentUser' => $currentUser,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $currentUser = ActiveUser::from($request);

        if ($currentUser === null || ActiveUser::isGuest($currentUser)) {
            return redirect('/');
        }

        $validated = $request->validate([
            'current_password' => ['nullable', 'string', 'max:72'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.confirmed' => 'Las dos contrasenas no coinciden.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
        ]);

        $usuario = User::query()->where('username', (string) $currentUser['username'])->first();

        if ($usuario === null) {
            return redirect('/')->with('error', 'Tu cuenta ya no existe.');
        }

        /*
         * Cambiar la contrasena de una cuenta ajena exige saber la actual.
         *
         * Antes bastaba con tener la sesion abierta (un aula con el equipo
         * compartido, una cookie olvidada): cualquiera podia cambiarla y dejar
         * fuera al dueno sin conocer su contrasena.
         *
         * Se exime solo el primer cambio obligatorio, donde la contrasena actual
         * es la temporal que se acaba de escribir para entrar.
         */
        if (! $this->debeCambiar((string) $usuario->username)) {
            $actual = (string) ($validated['current_password'] ?? '');

            if ($actual === '' || ! password_verify($actual, (string) $usuario->password)) {
                return back()->withErrors([
                    'current_password' => 'Escribe tu contrasena actual para poder cambiarla.',
                ]);
            }
        }

        // La contrasena nueva no puede ser la misma que le generaron: si no, el
        // cambio no serviria de nada.
        if (password_verify((string) $validated['password'], (string) $usuario->password)) {
            return back()->withErrors([
                'password' => 'Elige una contrasena distinta a la que te asignaron.',
            ]);
        }

        $usuario->password = (string) $validated['password'];
        $usuario->must_change_password = false;
        $usuario->save();

        return redirect('/')->with('success', 'Contrasena actualizada. Ya puedes usar tu cuenta.');
    }

    private function debeCambiar(string $username): bool
    {
        return (bool) User::query()
            ->where('username', $username)
            ->value('must_change_password');
    }
}
