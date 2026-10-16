<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Pase de acceso a un escritorio.
 *
 * PROBLEMA QUE RESUELVE
 *
 * Caddy comprueba el acceso antes de entregar un escritorio, preguntando a
 * /internal/proxy-auth. Esa comprobacion dependia de la cookie de sesion, pero la
 * cookie no siempre viaja al subdominio del escritorio, asi que el usuario
 * recibia 403 aunque estuviera dentro de la aplicacion.
 *
 * El pase no depende de cookies: es un enlace firmado que viaja en la propia
 * direccion. Lo emite la aplicacion al pulsar "Contenedor" y lo valida el mismo
 * punto de control.
 *
 * PROPIEDADES
 *
 *   - Firmado con APP_KEY: no se puede fabricar.
 *   - Con caducidad: una direccion copiada deja de servir.
 *   - Atado al usuario y a su escritorio: el pase de uno no abre el de otro.
 *
 * A diferencia de la comprobacion por cookie, esto SI distingue quien entra: sin
 * un pase emitido por la aplicacion, la respuesta es 403.
 */
class DesktopPass
{
    /** Minutos de validez. Cubre la jornada de trabajo y las reconexiones. */
    private const TTL_MINUTES = 480;

    /** Nombre del parametro en la direccion. */
    public const PARAM = 'pase';

    /**
     * Emite el pase para un usuario y su escritorio.
     */
    public function para(string $username, string $container): string
    {
        if ($username === '' || $container === '') {
            return '';
        }

        return URL::temporarySignedRoute(
            'escritorio.pase',
            now()->addMinutes(self::TTL_MINUTES),
            ['usuario' => $username, 'contenedor' => $container]
        );
    }

    /**
     * Usuario indicado por un pase con firma y caducidad validas.
     *
     * Devuelve null si el pase no es valido. No comprueba el escritorio: para eso
     * esta valido(), porque el escritorio solo se conoce por la cabecera que pone
     * el proxy.
     */
    public function usuario(Request $request): ?string
    {
        $consulta = $this->consultaDelPase($request);

        if ($consulta === null) {
            return null;
        }

        $usuario = trim((string) ($consulta['usuario'] ?? ''));

        return $usuario !== '' ? $usuario : null;
    }

    /**
     * Comprueba que el pase sea de ESTE usuario y ESTE escritorio.
     */
    public function valido(Request $request, string $username, string $container): bool
    {
        $consulta = $this->consultaDelPase($request);

        if ($consulta === null) {
            return false;
        }

        return trim((string) ($consulta['usuario'] ?? '')) === $username
            && trim((string) ($consulta['contenedor'] ?? '')) === $container;
    }

    /**
     * Parametros del pase, solo si la firma y la caducidad son correctas.
     *
     * @return array<string, mixed>|null
     */
    private function consultaDelPase(Request $request): ?array
    {
        $pase = trim((string) $request->query(self::PARAM, ''));

        if ($pase === '') {
            return null;
        }

        // El pase es una direccion COMPLETA (https://dominio/escritorio/pase?...
        // &signature=...). Hay que reconstruirla tal cual: si se validara contra
        // la direccion de la peticion en curso, la firma compararia dominios
        // distintos (el del escritorio con el de la aplicacion) y no cuadraria.
        $absoluta = str_starts_with($pase, 'http') ? $pase : url($pase);

        $peticion = Request::create($absoluta, 'GET');

        // Laravel comprueba aqui la firma y la caducidad.
        if (! URL::hasValidSignature($peticion)) {
            return null;
        }

        return $peticion->query->all();
    }
}
