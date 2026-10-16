<?php

namespace App\Http\Controllers;

use App\Support\ActiveUser;
use App\Support\ContainerResolver;
use App\Support\DesktopPass;
use App\Support\OllamaClient;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Acceso a los escritorios Webtop virtualizados.
 */
class ContainerController extends Controller
{
    public function __construct(
        private readonly ContainerResolver $resolver,
        private readonly OllamaClient $ollama,
        private readonly SecurityAudit $audit,
        private readonly DesktopPass $pase,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $currentUser = ActiveUser::from($request);

        if ($currentUser === null) {
            return redirect('/')->with('error', 'Tu cuenta fue desactivada o la sesion ya no es valida.');
        }

        $guestRemainingSeconds = null;

        if (ActiveUser::isGuest($currentUser)) {
            $guestRemainingSeconds = max(0, (int) $request->session()->get('guest_expires_at', 0) - time());
        }

        return view('contenedor', [
            'currentUser' => $currentUser,
            'guestRemainingSeconds' => $guestRemainingSeconds,
            'ollamaEnabled' => $this->ollama->isEnabled(),
            'ollamaModel' => $this->ollama->model(),
            'ollamaVisible' => ActiveUser::isAdmin($currentUser),
        ]);
    }

    public function launch(Request $request): RedirectResponse
    {
        $currentUser = ActiveUser::from($request);

        if ($currentUser === null) {
            return redirect('/')->with('error', 'Tu cuenta fue desactivada o la sesion ya no es valida.');
        }

        $url = $this->resolver->urlFor($currentUser);

        // El enlace lleva un pase firmado.
        //
        // Motivo: Caddy comprueba el acceso ANTES de entregar el escritorio, pero
        // la cookie de sesion no siempre viaja al subdominio. El pase resuelve eso
        // de raiz, porque no depende de cookies: va en la propia direccion.
        //
        // Es un enlace firmado con la clave de la aplicacion y con caducidad, y va
        // atado al usuario y a su escritorio. Copiarlo y abrirlo en otro navegador
        // no sirve: caduca, y ademas la aplicacion comprueba que el escritorio sea
        // el suyo.
        $contenedor = $this->resolver->containerNameFromUrl($url);
        $pase = $this->pase->para((string) $currentUser['username'], (string) $contenedor);

        $destino = $url . (str_contains($url, '?') ? '&' : '?') . 'pase=' . urlencode($pase);

        $this->audit->log($request, 'webtop.launch', $currentUser, [
            'container_url' => $url,
            'con_pase' => $pase !== '',
        ]);

        // Cada escritorio vive en su propio subdominio: redireccion externa.
        return redirect()->away($destino);
    }
}
