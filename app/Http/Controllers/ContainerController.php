<?php

namespace App\Http\Controllers;

use App\Models\WebtopContainer;
use App\Support\ActiveUser;
use App\Support\ContainerResolver;
use App\Support\DesktopPass;
use App\Support\OllamaClient;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

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

        // Solo el administrador recibe la lista de escritorios para probarlos.
        // El resto de la vista no la necesita y no debe verla.
        $esAdmin = ActiveUser::isAdmin($currentUser);

        return view('contenedor', [
            'currentUser' => $currentUser,
            'guestRemainingSeconds' => $guestRemainingSeconds,
            'ollamaEnabled' => $this->ollama->isEnabled(),
            'ollamaModel' => $this->ollama->model(),
            'ollamaVisible' => $esAdmin,
            'adminDesktops' => $esAdmin ? $this->escritoriosParaAdmin() : [],
            'currentDesktop' => $esAdmin
                ? $this->resolver->containerNameFromUrl($this->resolver->urlFor($currentUser))
                : null,
        ]);
    }

    /**
     * Escritorios registrados, para el selector del administrador.
     *
     * Se ordena por nombre para que la lista sea estable entre cargas.
     *
     * Solo se envia el nombre. La direccion interna de cada escritorio no sale
     * del servidor: al navegador no le aporta nada y no conviene publicar la red
     * local en la pagina.
     *
     * @return list<array{name: string}>
     */
    private function escritoriosParaAdmin(): array
    {
        try {
            $filas = WebtopContainer::query()->orderBy('name')->get();
        } catch (Throwable) {
            return [];
        }

        $lista = [];

        foreach ($filas as $fila) {
            $nombre = trim((string) $fila->name);

            if ($nombre === '') {
                continue;
            }

            $lista[] = ['name' => $nombre];
        }

        return $lista;
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

        // El administrador puede pedir CUALQUIER escritorio por su nombre, para
        // probarlos todos sin crear una cuenta por cada uno. A cualquier otro
        // usuario se le ignora el parametro: sigue viendo solo el suyo.
        $pedido = trim((string) $request->query('contenedor', ''));

        if ($pedido !== '' && ActiveUser::isAdmin($currentUser)) {
            $destino = WebtopContainer::query()->where('name', $pedido)->first();

            // Se comprueba que exista: si no, se mantiene el suyo en lugar de
            // emitir un pase hacia un escritorio fantasma.
            if ($destino !== null) {
                $url = (string) $destino->url;
                $contenedor = $pedido;
            }
        }
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
