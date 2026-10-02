<?php

namespace App\Http\Controllers;

use App\Support\ActiveUser;
use App\Support\ContainerResolver;
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

        $this->audit->log($request, 'webtop.launch', $currentUser, ['container_url' => $url]);

        return redirect()->away($url);
    }
}
