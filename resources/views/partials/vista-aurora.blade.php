{{--
    La Aurora de VirtHub: la función secreta de los temas de Windows Vista.

    No es una ventana a pantalla completa: es un FONDO animado. Al activarla, el
    video de la aurora se pone detrás de la página, como si el escritorio de
    Vista estuviera vivo.

    Cómo se activa (solo con el tema Vista, en cualquiera de sus colores):
      - triple clic sobre el logo de VirtHub (el logo ya no es un enlace)
      - o Ctrl + Shift + A

    La elección se recuerda en el navegador. En los demás temas no hace nada.
--}}

<video class="aurora-fondo" id="auroraFondo"
       src="{{ asset('pics/temas/vistaaurora.mp4') }}"
       muted loop playsinline preload="none" aria-hidden="true"></video>

<div class="aurora-velo" id="auroraVelo" aria-hidden="true"></div>

<div class="aurora-aviso" id="auroraAviso" hidden>
    <span class="aurora-aviso-texto">Aurora activada</span>
    <button type="button" class="aurora-aviso-cerrar" aria-label="Quitar la aurora">Quitar</button>
</div>

<style>
    /*
     * Capas: el video va por detrás de todo y el velo encima de él, para que el
     * texto siga leyéndose. Los dos son fixed y no reciben clics.
     */
    .aurora-fondo,
    .aurora-velo {
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.9s ease;
    }

    .aurora-fondo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        filter: saturate(1.05);
    }

    .aurora-velo {
        background: rgba(0, 0, 0, 0.22);
        display: none;
    }

    .aura-activa .aurora-fondo,
    .aura-activa .aurora-velo {
        opacity: 1;
    }

    .aura-activa .aurora-velo {
        display: block;
    }

    /*
     * Para que el video se vea, el color oscuro tiene que ir en <html> y el body
     * quedar transparente: si el body pinta su fondo, tapa el video, que va en
     * una capa por debajo del contenido.
     */
    html.aura-activa {
        background-color: #041018 !important;
    }

    .aura-activa body {
        background-image: none !important;
        background-color: transparent !important;
    }

    .aurora-aviso {
        position: fixed;
        left: 50%;
        bottom: 18px;
        transform: translateX(-50%);
        z-index: 99998;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 9px 7px 14px;
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 999px;
        background: rgba(6, 16, 24, 0.82);
        color: #eaf3fb;
        font: 12px Monocraft Nerd Font, monospace;
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.4);
        transition: opacity 0.4s ease;
    }

    .aurora-aviso[hidden] {
        display: none;
    }

    .aurora-aviso-cerrar {
        padding: 3px 10px;
        border: 1px solid rgba(255, 255, 255, 0.28);
        border-radius: 999px;
        background: transparent;
        color: inherit;
        font: inherit;
        cursor: pointer;
    }

    .aurora-aviso-cerrar:hover {
        background: rgba(255, 255, 255, 0.14);
    }

    @media (prefers-reduced-motion: reduce) {
        .aurora-fondo,
        .aurora-velo {
            transition: none;
        }
    }
</style>

<script>
(() => {
    'use strict';

    const video = document.getElementById('auroraFondo');
    const aviso = document.getElementById('auroraAviso');

    if (!video) return;

    const CLAVE = 'virthub-aurora';
    const marca = document.querySelector('.brand-mark');

    const esVista = () => document.documentElement.dataset.era === 'vista';

    let activa = false;
    let esperaAviso = null;

    const guardar = (valor) => {
        try {
            localStorage.setItem(CLAVE, valor ? '1' : '0');
        } catch (e) {
            // Sin almacenamiento, la aurora dura hasta recargar.
        }
    };

    const recordado = () => {
        try {
            return localStorage.getItem(CLAVE) === '1';
        } catch (e) {
            return false;
        }
    };

    const aplicar = (encender, avisar) => {
        if (!esVista()) {
            activa = false;
            document.documentElement.classList.remove('aura-activa');
            return;
        }

        activa = encender;
        document.documentElement.classList.toggle('aura-activa', encender);

        if (encender) {
            video.play().catch(() => {
                // Si el navegador bloquea el video, queda el fondo oscuro.
            });
        } else {
            video.pause();
        }

        guardar(encender);

        if (avisar && aviso) {
            aviso.hidden = false;
            clearTimeout(esperaAviso);
            esperaAviso = setTimeout(() => { aviso.hidden = true; }, 3200);
        }
    };

    const alternar = () => aplicar(!activa, true);

    // --- Activación 1: triple clic en el logo ---------------------------
    let clics = 0;
    let ultimo = 0;

    if (marca) {
        marca.addEventListener('click', () => {
            const ahora = Date.now();

            clics = ahora - ultimo > 800 ? 1 : clics + 1;
            ultimo = ahora;

            if (clics >= 3) {
                clics = 0;
                alternar();
            }
        });
    }

    // --- Activación 2: Ctrl + Shift + A ---------------------------------
    document.addEventListener('keydown', (evento) => {
        if (evento.ctrlKey && evento.shiftKey && evento.key.toLowerCase() === 'a') {
            evento.preventDefault();
            alternar();
        }
    });

    // --- El botón del aviso la quita ------------------------------------
    if (aviso) {
        const boton = aviso.querySelector('.aurora-aviso-cerrar');

        if (boton) {
            boton.addEventListener('click', () => aplicar(false, false));
        }
    }

    // --- Al cambiar de tema, se apaga sola ------------------------------
    new MutationObserver(() => {
        if (!esVista() && activa) {
            aplicar(false, false);
        }
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-era'] });

    // --- Con la pestaña oculta, el video se pausa -----------------------
    document.addEventListener('visibilitychange', () => {
        if (video.hidden) return;

        if (document.hidden) {
            video.pause();
        } else if (activa) {
            video.play().catch(() => {});
        }
    });

    // --- Al volver a la página, si se había activado --------------------
    if (esVista() && recordado()) {
        aplicar(true, false);
    }
})();
</script>
