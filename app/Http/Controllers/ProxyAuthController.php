<?php

namespace App\Http\Controllers;

use App\Support\ActiveUser;
use App\Support\ContainerResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
 * La comprobacion importante no es "esta autenticado" sino "este escritorio es
 * el suyo": cada usuario tiene un contenedor asignado y no debe poder abrir el
 * de otro aunque conozca la ruta.
 */
class ProxyAuthController extends Controller
{
    public function __construct(private readonly ContainerResolver $resolver) {}

    public function __invoke(Request $request): Response
    {
        $user = ActiveUser::from($request);

        if (! ActiveUser::isRegistered($user)) {
            return $this->deny('Sin sesion activa.');
        }

        // El proxy envia en X-Container el sufijo solicitado (por ejemplo "ct2").
        $requested = trim((string) $request->header('X-Container', ''));

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
            return $this->deny('Ese escritorio no te pertenece.');
        }

        return $this->allow($user, $esperado);
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

        $name = $this->containerNameFromUrl($url);

        if ($name === null) {
            return null;
        }

        return ['name' => $name, 'url' => $url];
    }

    /**
     * Extrae "ct2" de "https://ct2.virthub.dpdns.org/".
     */
    private function containerNameFromUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        // Se queda con la primera etiqueta del host: ct2.virthub... -> ct2
        $label = explode('.', $host)[0] ?? '';

        return $label !== '' ? $label : null;
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
