<?php

namespace App\Http\Controllers;

use App\Services\UserStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asistente de instalacion inicial.
 *
 * Se accede con la clave privada INSTALL_KEY o, si el entorno lo permite
 * explicitamente (INSTALL_ALLOW_LOCALHOST=true) y todavia no hay admin, desde
 * el propio host.
 */
class InstallController extends Controller
{
    public function __construct(private readonly UserStore $users) {}

    public function show(Request $request): View
    {
        abort_unless($this->isAuthorized($request), 404);

        return view('install', [
            'installComplete' => $this->users->hasAdminAccount(),
            'statusMessage' => session('status_message'),
            'installationKey' => (string) $request->query('key', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isAuthorized($request), 404);

        if ($this->users->hasAdminAccount()) {
            abort(403, 'La instalacion ya fue completada.');
        }

        $validated = $request->validate([
            'admin_username' => ['required', 'string', 'max:50'],
            'admin_password' => ['required', 'confirmed', 'min:8'],
        ]);

        $this->users->createOrUpdateAdmin(
            (string) $validated['admin_username'],
            (string) $validated['admin_password']
        );

        return redirect('/')->with('status_message', 'Instalación completada. Ya puedes iniciar sesión con el administrador creado.');
    }

    /**
     * El instalador solo se abre con la clave, o sin ella cuando el operador lo
     * autoriza de forma explicita para el host local y aun no existe admin.
     */
    /**
     * Autorizacion del instalador.
     *
     * Orden de comprobacion:
     *   1. Si se envio la clave correcta, se autoriza.
     *   2. Si ya existe un admin, el instalador queda cerrado.
     *   3. Solo si NO hay clave configurada se admite el acceso sin clave:
     *      en testing para poder ejercitar el asistente, o en el propio host
     *      cuando el operador activa INSTALL_ALLOW_LOCALHOST=true.
     *
     * Una clave configurada nunca se puede saltar, ni en pruebas.
     */
    private function isAuthorized(Request $request): bool
    {
        $installationKey = (string) config('installation.key', '');
        $providedKey = (string) ($request->query('key', $request->input('key', '')));

        if ($installationKey !== '' && $providedKey !== '') {
            return hash_equals($installationKey, $providedKey);
        }

        if ($installationKey !== '') {
            return false;
        }

        if ($this->users->hasAdminAccount()) {
            return false;
        }

        if (app()->environment('testing')) {
            return true;
        }

        return (bool) config('virthub.installation.allow_localhost_without_key')
            && $this->isLocalRequest($request);
    }

    private function isLocalRequest(Request $request): bool
    {
        return in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true);
    }
}
