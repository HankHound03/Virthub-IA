<?php

namespace App\Support;

/**
 * Avisos que no se pierden por el camino.
 *
 * POR QUE EXISTE
 *
 * Laravel muestra los mensajes con "flash": un dato que vive en la sesion y se
 * borra en la SIGUIENTE peticion que reciba la cookie. El problema es que "la
 * siguiente peticion" no siempre es la pagina que el usuario va a mirar: puede
 * ser una precarga de enlace, una peticion en segundo plano o una pestana
 * abierta. Cuando eso pasa, el mensaje se consume sin que nadie lo vea y el
 * usuario se queda sin saber que ocurrio.
 *
 * Paso justo con el antivirus: el archivo se rechazaba correctamente y el aviso
 * se generaba, pero no llegaba a verse.
 *
 * COMO FUNCIONA
 *
 * Se guarda en la sesion como un dato normal, sin caducidad de una peticion, y
 * solo lo borra la pagina que lo pinta (tomar()). Asi sobrevive a cualquier
 * peticion intermedia.
 */
final class Aviso
{
    private const CLAVE = 'virthub_aviso';

    /**
     * Deja un aviso para la proxima pagina que lo muestre.
     *
     * @param  string  $tipo  'error' o 'success'
     */
    public static function poner(string $tipo, string $texto): void
    {
        $texto = trim($texto);

        if ($texto === '') {
            return;
        }

        session()->put(self::CLAVE, [
            'tipo' => $tipo === 'success' ? 'success' : 'error',
            'texto' => $texto,
        ]);
    }

    /**
     * Devuelve el aviso pendiente y lo borra.
     *
     * @return array{tipo: string, texto: string}|null
     */
    public static function tomar(): ?array
    {
        $aviso = session()->pull(self::CLAVE);

        if (! is_array($aviso) || ! isset($aviso['texto'])) {
            return null;
        }

        return [
            'tipo' => (string) ($aviso['tipo'] ?? 'error'),
            'texto' => (string) $aviso['texto'],
        ];
    }
}
