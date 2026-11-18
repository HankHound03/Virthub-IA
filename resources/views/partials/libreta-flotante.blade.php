{{--
    Libreta digital en flotante.

    Un botón redondo encima del botón del chat, y un panel que carga /libreta en
    un iframe. Sin fondo difuminado: el resto de la página se sigue viendo y se
    puede usar.

    El panel se arrastra por su cabecera y se queda donde lo dejes: la posición
    se guarda en el navegador. Doble clic en la cabecera lo devuelve a su sitio.
--}}

<div class="libreta-flotante" id="libretaFlotante">
    <button type="button" class="libreta-flotante-boton" id="libretaAbrir"
            title="Abrir la libreta" aria-label="Abrir la libreta" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H18a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6.5A1.5 1.5 0 0 1 5 19.5z"/>
            <path d="M8 3v18"/>
            <path d="M11.5 7.5h4"/>
            <path d="M11.5 11h4"/>
            <path d="M11.5 14.5h2.5"/>
        </svg>
    </button>

    <section class="libreta-flotante-panel" id="libretaPanel" aria-label="Libreta" hidden>
        <div class="libreta-flotante-cabecera" id="libretaArrastre" title="Arrastra para mover · doble clic para volver">
            <span class="libreta-flotante-puntos" aria-hidden="true">⋮⋮</span>
            <strong>Libreta</strong>
            <button type="button" class="libreta-flotante-cerrar" id="libretaCerrar" aria-label="Cerrar la libreta">×</button>
        </div>

        <iframe id="libretaMarco" title="Libreta digital" src="about:blank" loading="lazy"></iframe>
    </section>
</div>

<style>
    /* El botón va encima del de chat: el chat ocupa la esquina inferior derecha. */
    .libreta-flotante {
        position: fixed;
        right: 22px;
        bottom: 92px;
        z-index: 6000;
        display: flex;
        flex-direction: column-reverse;
        align-items: flex-end;
        gap: 10px;
        pointer-events: none;
    }

    .libreta-flotante > * {
        pointer-events: auto;
    }

    .libreta-flotante-boton {
        width: 52px;
        height: 52px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--vh-btn-border, var(--vh-border));
        border-radius: 50%;
        background: var(--vh-btn-bg);
        color: var(--vh-btn-text, var(--vh-text));
        cursor: pointer;
        box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
        transition: transform 0.15s ease, filter 0.15s ease;
    }

    .libreta-flotante-boton:hover {
        transform: translateY(-2px);
        filter: brightness(1.08);
    }

    .libreta-flotante-boton[aria-expanded="true"] {
        filter: brightness(1.15);
    }

    /*
     * Panel flotante, SIN fondo difuminado: se ve la página de detrás.
     * Va en posición fija con coordenadas propias, para poder arrastrarlo sin
     * arrastrar el botón.
     */
    .libreta-flotante-panel {
        position: fixed;
        left: auto;
        right: 22px;
        bottom: 154px;
        width: min(760px, calc(100vw - 44px));
        height: min(560px, calc(100vh - 200px));
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--vh-border);
        border-radius: var(--vh-btn-radius, 10px);
        background-color: var(--vh-surface-strong);
        box-shadow: 0 18px 44px rgba(0, 0, 0, 0.42);
        animation: libretaEntra 0.18s ease-out;
    }

    .libreta-flotante-panel[hidden] {
        display: none;
    }

    .libreta-flotante-panel.arrastrado {
        right: auto;
        bottom: auto;
        animation: none;
    }

    @keyframes libretaEntra {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .libreta-flotante-cabecera {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-bottom: 1px solid var(--vh-border);
        color: var(--vh-text);
        font-family: Monocraft Nerd Font, monospace;
        font-size: 12px;
        cursor: move;
        touch-action: none;
        user-select: none;
    }

    .libreta-flotante-cabecera strong {
        flex: 1;
    }

    .libreta-flotante-puntos {
        color: var(--vh-text-soft);
        letter-spacing: -2px;
        font-size: 13px;
    }

    .libreta-flotante-cerrar {
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--vh-border);
        border-radius: 6px;
        background: transparent;
        color: var(--vh-text);
        font-size: 16px;
        line-height: 1;
        cursor: pointer;
    }

    .libreta-flotante-cerrar:hover {
        background: var(--vh-btn-hover-bg, rgba(255, 255, 255, 0.12));
    }

    .libreta-flotante-panel iframe {
        flex: 1;
        width: 100%;
        border: none;
        background: transparent;
    }

    @media (max-width: 700px) {
        .libreta-flotante {
            right: 14px;
            bottom: 84px;
        }

        .libreta-flotante-boton {
            width: 46px;
            height: 46px;
        }

        .libreta-flotante-panel {
            width: calc(100vw - 28px);
            height: min(520px, calc(100vh - 190px));
        }
    }
</style>

<script>
(() => {
    'use strict';

    const boton = document.getElementById('libretaAbrir');
    const panel = document.getElementById('libretaPanel');
    const cerrar = document.getElementById('libretaCerrar');
    const marco = document.getElementById('libretaMarco');
    const asa = document.getElementById('libretaArrastre');

    if (!boton || !panel || !marco || !asa) return;

    const url = @json(url('/libreta') . '?flotante=1');
    const CLAVE = 'virthub-libreta-posicion';
    const MARGEN = 8;

    // ------------------------------------------------------------------
    // Posición: se recuerda entre visitas
    // ------------------------------------------------------------------

    const limitar = (valor, minimo, maximo) => Math.min(Math.max(valor, minimo), maximo);

    const colocar = (izquierda, arriba) => {
        const ancho = panel.offsetWidth || 380;
        const alto = panel.offsetHeight || 400;

        const x = limitar(izquierda, MARGEN, Math.max(MARGEN, window.innerWidth - ancho - MARGEN));
        const y = limitar(arriba, MARGEN, Math.max(MARGEN, window.innerHeight - alto - MARGEN));

        panel.classList.add('arrastrado');
        panel.style.left = x + 'px';
        panel.style.top = y + 'px';
    };

    const guardarPosicion = () => {
        const rect = panel.getBoundingClientRect();

        try {
            localStorage.setItem(CLAVE, JSON.stringify({ x: Math.round(rect.left), y: Math.round(rect.top) }));
        } catch (e) {
            // Si el navegador no deja guardar, solo se pierde la posición.
        }
    };

    const restaurarPosicion = () => {
        let guardada = null;

        try {
            guardada = JSON.parse(localStorage.getItem(CLAVE) || 'null');
        } catch (e) {
            guardada = null;
        }

        if (guardada && Number.isFinite(guardada.x) && Number.isFinite(guardada.y)) {
            colocar(guardada.x, guardada.y);
            return;
        }

        // Sin posición guardada: pegado a la derecha, encima del botón.
        panel.classList.remove('arrastrado');
        panel.style.left = '';
        panel.style.top = '';
    };

    // ------------------------------------------------------------------
    // Arrastre
    // ------------------------------------------------------------------

    let arrastrando = false;
    let desplazamientoX = 0;
    let desplazamientoY = 0;

    asa.addEventListener('pointerdown', (evento) => {
        if (evento.target.closest('.libreta-flotante-cerrar')) return;

        const rect = panel.getBoundingClientRect();

        arrastrando = true;
        desplazamientoX = evento.clientX - rect.left;
        desplazamientoY = evento.clientY - rect.top;

        // Se fija la posición actual antes de mover, para que no salte.
        colocar(rect.left, rect.top);

        // Capturar el puntero mejora el arrastre, pero si el navegador no lo
        // permite (o el puntero ya no está activo) no debe romper nada.
        try {
            asa.setPointerCapture(evento.pointerId);
        } catch (e) {
            // Se sigue arrastrando con pointermove.
        }

        evento.preventDefault();
    });

    asa.addEventListener('pointermove', (evento) => {
        if (!arrastrando) return;

        colocar(evento.clientX - desplazamientoX, evento.clientY - desplazamientoY);
    });

    const soltar = (evento) => {
        if (!arrastrando) return;

        arrastrando = false;
        guardarPosicion();

        try {
            asa.releasePointerCapture(evento.pointerId);
        } catch (e) {
            // Si ya se soltó, da igual.
        }
    };

    asa.addEventListener('pointerup', soltar);
    asa.addEventListener('pointercancel', soltar);

    // Doble clic en la cabecera: vuelve a su sitio de siempre
    asa.addEventListener('dblclick', () => {
        try {
            localStorage.removeItem(CLAVE);
        } catch (e) {
            // Nada que borrar.
        }

        restaurarPosicion();
    });

    // Al cambiar el tamaño de la ventana, que no se quede fuera
    window.addEventListener('resize', () => {
        if (panel.hidden) return;

        const rect = panel.getBoundingClientRect();
        colocar(rect.left, rect.top);
    });

    // ------------------------------------------------------------------
    // Abrir y cerrar
    // ------------------------------------------------------------------

    const abrir = () => {
        if (marco.getAttribute('src') !== url) {
            marco.setAttribute('src', url);
        }

        panel.hidden = false;
        boton.setAttribute('aria-expanded', 'true');

        restaurarPosicion();

        document.addEventListener('keydown', alPulsarTecla);
    };

    const ocultar = () => {
        panel.hidden = true;
        boton.setAttribute('aria-expanded', 'false');
        document.removeEventListener('keydown', alPulsarTecla);
    };

    function alPulsarTecla(evento) {
        if (evento.key === 'Escape') {
            ocultar();
        }
    }

    boton.addEventListener('click', () => {
        if (panel.hidden) {
            abrir();
        } else {
            ocultar();
        }
    });

    if (cerrar) {
        cerrar.addEventListener('click', ocultar);
    }
})();
</script>
