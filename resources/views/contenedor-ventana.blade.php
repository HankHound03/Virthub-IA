<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Escritorio - VirtHub</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
    <style>
        /*
         * Ventana del escritorio.
         *
         * Sin cabecera, sin menu y sin margenes: solo el escritorio. La barra de
         * direcciones de esta ventana muestra NUESTRO dominio, no el subdominio
         * del contenedor, que va dentro del marco.
         */
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            background-color: #0b1520;
        }

        #viewer {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            background-color: #0b1520;
        }

        /* Controles discretos, en una esquina, que no estorban al escritorio. */
        .ventana-controles {
            position: fixed;
            right: 10px;
            top: 10px;
            display: flex;
            gap: 6px;
            z-index: 10;
            opacity: 0.35;
            transition: opacity 0.2s ease;
        }

        .ventana-controles:hover,
        .ventana-controles:focus-within {
            opacity: 1;
        }

        .ventana-controles button {
            padding: 7px 11px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 9px;
            background-color: rgba(12, 24, 36, 0.78);
            color: #eaf4ff;
            font: 12px Monocraft Nerd Font, monospace;
            cursor: pointer;
            backdrop-filter: blur(8px);
        }

        .ventana-controles button:hover {
            background-color: rgba(30, 52, 72, 0.9);
        }
    </style>
</head>
<body>
    <div class="ventana-controles">
        <button type="button" id="pantallaCompleta" title="Quitar el marco del navegador">Pantalla completa</button>
        <button type="button" onclick="window.close()" title="Cerrar la ventana">Cerrar</button>
    </div>

    {{--
        Mismo sandbox que la pagina del contenedor: el escritorio no puede salirse
        del marco ni navegar al nivel superior.
    --}}
    <iframe id="viewer"
            src="{{ $launchUrl }}"
            allow="microphone *; camera *; fullscreen *"
            sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals"></iframe>

    <script>
        const boton = document.getElementById('pantallaCompleta');
        const contenedor = document.getElementById('viewer');

        boton?.addEventListener('click', async () => {
            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                    boton.textContent = 'Pantalla completa';
                } else {
                    await document.documentElement.requestFullscreen();
                    boton.textContent = 'Salir de pantalla completa';
                }
            } catch (error) {
                // Algunos navegadores lo bloquean; no se hace nada.
            }
        });
    </script>
</body>
</html>
