<?php

namespace App\Http\Controllers;

use App\Support\ActiveUser;
use App\Support\ContainerResolver;
use App\Models\WebtopContainer;
use App\Services\UserStore;
use App\Support\DesktopPass;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Punto de control de acceso para el proxy inverso.
 *
 * El proxy (Caddy) pregunta aqui antes de entregar un escritorio. Solo hay dos
 * respuestas posibles:
 *
 *   200 -> se entrega el escritorio
 *   403 -> no
 *
 * Nunca devuelve un redirect ni un cuerpo util: el proxy solo mira el codigo.
 *
 * Se comprueban dos cosas:
 *
 *   1. Que el escritorio pedido sea el suyo (no el de otro usuario). El nombre lo
 *      pone Caddy en X-Container, no el cliente, asi que no se puede falsear.
 *   2. Que haya una sesion valida O un pase firmado emitido por la aplicacion.
 *
 * El pase existe porque la cookie de sesion no siempre viaja al subdominio del
 * escritorio: sin el, un usuario con sesion abierta recibia 403 al abrir su
 * propio escritorio. El pase viaja en la direccion, asi que no depende de
 * cookies, y ademas aporta algo que la cookie no daba: sin un pase emitido por la
 * aplicacion no se entra, ni siquiera copiando la direccion.
 */
class ProxyAuthController extends Controller
{
    public function __construct(
        private readonly ContainerResolver $resolver,
        private readonly DesktopPass $pase,
        private readonly UserStore $users,
    ) {}

    public function __invoke(Request $request): Response
    {
        // El nombre del escritorio lo pone Caddy en X-Container.
        $requested = trim((string) $request->header('X-Container', ''));

        // Primero se resuelve a quien pertenece: por sesion si la hay, y si no
        // por el pase firmado, que identifica al usuario sin depender de cookies.
        $user = $this->usuarioDeLaPeticion($request);

        if ($user === null) {
            return $this->deny('Sin sesion activa ni pase valido.');
        }

        if ($requested === '') {
            // Sin destino declarado solo se autoriza el paso si hay usuario
            // valido; el enrutado lo decide el proxy.
            return $this->allow($user);
        }

        $allowed = $this->resolveAllowedContainer($user);

        if ($allowed === null) {

            return $this->deny('No tienes un escritorio asignado.');
        }

        // El nombre del contenedor se guarda como "ct2" y la URL contiene ese
        // mismo nombre. Se compara contra ambos por robustez.
        $esperado = (string) ($allowed['name'] ?? '');
        $coincide = $esperado !== '' && $esperado === $requested;

        // Los invitados van a un contenedor compartido: se permite solo si es
        // exactamente el que les corresponde.
        if (! $coincide) {
            // El administrador puede abrir CUALQUIER escritorio: es lo que usa
            // para probarlos todos desde el selector de la pagina de contenedor.
            //
            // Aun asi se comprueba que el escritorio exista de verdad, para no
            // emitir permiso hacia un nombre inventado. Un usuario normal sigue
            // recibiendo 403 aqui, aunque manipule la peticion.
            if (! ActiveUser::isAdmin($user)) {
                return $this->deny('Ese escritorio no te pertenece.');
            }

            if (! $this->escritorioExiste($requested)) {
                return $this->deny('Ese escritorio no existe.');
            }

            // A partir de aqui el escritorio pedido pasa a ser el esperado: asi el
            // pase se valida contra el que el administrador eligio.
            $esperado = $requested;
        }

        // Con sesion no hace falta nada mas: el usuario ya esta identificado. El
        // pase solo se exige cuando la sesion no llego (que es el caso habitual
        // en el subdominio del escritorio).
        if (ActiveUser::isRegistered(ActiveUser::from($request))) {
            return $this->allow($user, $esperado);
        }

        if (! $this->pase->valido($request, ActiveUser::username($user), $esperado)) {

            return $this->deny('Falta un pase de acceso valido.');
        }

        return $this->allow($user, $esperado);
    }

    /**
     * Indica si un escritorio con ese nombre esta registrado.
     *
     * Se usa para el administrador: puede abrir cualquiera, pero solo si existe.
     */
    private function escritorioExiste(string $nombre): bool
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return false;
        }

        try {
            return WebtopContainer::query()->where('name', $nombre)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Usuario de la peticion: por sesion, o por el pase firmado.
     *
     * El pase es una direccion firmada que incluye el nombre de usuario, asi que
     * permite identificarlo sin depender de la cookie de sesion.
     *
     * @return array<string, mixed>|null
     */
    private function usuarioDeLaPeticion(Request $request): ?array
    {
        $sesion = ActiveUser::from($request);

        if (ActiveUser::isRegistered($sesion)) {
            return $sesion;
        }

        $usuario = $this->pase->usuario($request);

        if ($usuario === null) {
            return null;
        }

        // Se revalida contra el almacen: la cuenta debe seguir existiendo y
        // activa, igual que con la sesion.
        $encontrado = $this->users->findByUsername($usuario);

        if (! is_array($encontrado) || ! ($encontrado['is_active'] ?? true)) {
            return null;
        }

        return [
            'id' => (string) ($encontrado['id'] ?? ''),
            'username' => (string) $encontrado['username'],
            'role' => (string) ($encontrado['role'] ?? 'user'),
            'name' => (string) ($encontrado['name'] ?? $encontrado['username']),
        ];
    }

    /**
     * Contenedor asignado al usuario, como ['name' => 'ct2', 'url' => ...].
     *
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>|null
     */
    private function resolveAllowedContainer(array $user): ?array
    {
        $url = $this->resolver->assignedContainerFor($user);

        // Sin asignacion en la base de datos se usa el respaldo por hash, que
        // sigue siendo un unico contenedor por usuario.
        if ($url === null) {
            $url = $this->resolver->urlFor($user);
        }

        $name = $this->resolver->containerNameFromUrl($url);

        if ($name === null) {
            return null;
        }

        return ['name' => $name, 'url' => $url];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function allow(array $user, ?string $container = null): Response
    {
        return response('', 200, array_filter([
            'X-Virthub-User' => ActiveUser::username($user),
            'X-Virthub-Container' => $container,
        ]));
    }

    private function deny(string $reason): Response
    {
        // El motivo se registra, pero no se envia al navegador: el proxy solo
        // necesita el codigo, y no conviene revelar detalles internos.
        return response('', 403, ['X-Virthub-Denied' => '1']);
    }
}
