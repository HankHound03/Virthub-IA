{{--
    La Aurora de VirtHub: la función secreta de los temas de Windows Vista.

    Cómo se activa (solo con el tema Vista, en cualquiera de sus colores):
      - Ctrl + Shift + A
      - o triple clic sobre el logo de VirtHub

    Se abre a pantalla completa con el video de la aurora y se cierra con Esc o
    con un clic. En los demás temas no hace nada: el secreto es de Vista.
--}}

<div class="aurora-lienzo" id="auroraLienzo" hidden aria-hidden="true">
    <video class="aurora-video" id="auroraVideo"
           src="{{ asset('pics/temas/vistaaurora.mp4') }}"
           muted loop playsinline preload="none"></video>

    <p class="aurora-pista" id="auroraPista">Aurora de VirtHub · pulsa <kbd>Esc</kbd> para volver</p>
</div>

<style>
    .aurora-lienzo {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #000;
        opacity: 0;
        transition: opacity 0.55s ease;
        cursor: pointer;
        overflow: hidden;
    }

    .aurora-lienzo[hidden] {
        display: none;
    }

    .aurora-lienzo.visible {
        opacity: 1;
    }

    .aurora-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        /* Un pelo de desenfoque le quita el ruido de compresión al escalar. */
        filter: saturate(1.05);
    }

    .aurora-pista {
        position: absolute;
        bottom: 22px;
        left: 50%;
        transform: translateX(-50%);
        margin: 0;
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.45);
        color: rgba(255, 255, 255, 0.86);
        font: 12px Monocraft Nerd Font, monospace;
        letter-spacing: 0.4px;
        opacity: 0;
        transition: opacity 0.6s ease;
        pointer-events: none;
    }

    .aurora-lienzo.visible .aurora-pista {
        opacity: 1;
        transition-delay: 0.8s;
    }

    .aurora-pista kbd {
        padding: 1px 5px;
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 4px;
        font: inherit;
    }

    @media (prefers-reduced-motion: reduce) {
        .aurora-lienzo,
        .aurora-pista {
            transition: none;
        }
    }
</style>

<script>
(() => {
    'use strict';

    const lienzo = document.getElementById('auroraLienzo');
    const video = document.getElementById('auroraVideo');
    const marca = document.querySelector('.brand-mark');

    if (!lienzo || !video) return;

    const esVista = () => document.documentElement.dataset.era === 'vista';

    let abierto = false;
    let clics = 0;
    let ultimoClic = 0;

    const abrir = () => {
        // El secreto solo existe en Vista: en los demás temas, ni caso.
        if (!esVista() || abierto) return;

        abierto = true;
        lienzo.hidden = false;

        // requestAnimationFrame para que la transición de opacidad se note.
        requestAnimationFrame(() => lienzo.classList.add('visible'));

        video.currentTime = 0;
        video.play().catch(() => {
            // Si el navegador bloquea la reproducción, se queda el fondo negro
            // con la pista; no rompemos nada.
        });
    };

    const cerrar = () => {
        if (!abierto) return;

        abierto = false;
        lienzo.classList.remove('visible');

        window.setTimeout(() => {
            if (!abierto) {
                lienzo.hidden = true;
                video.pause();
                video.currentTime = 0;
            }
        }, 600);
    };

    // --- Activación 1: Ctrl + Shift + A ---------------------------------
    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && abierto) {
            evento.preventDefault();
            cerrar();
            return;
        }

        if (evento.ctrlKey && evento.shiftKey && evento.key.toLowerCase() === 'a') {
            evento.preventDefault();
            abrir();
        }
    });

    // --- Activación 2: triple clic en el logo ---------------------------
    if (marca) {
        marca.addEventListener('click', () => {
            const ahora = Date.now();

            // Más de 800 ms entre clics: se cuenta otra vez desde cero.
            clics = ahora - ultimoClic > 800 ? 1 : clics + 1;
            ultimoClic = ahora;

            if (clics >= 3) {
                clics = 0;
                abrir();
            }
        });
    }

    // --- Salir ----------------------------------------------------------
    lienzo.addEventListener('click', cerrar);
})();
</script>
