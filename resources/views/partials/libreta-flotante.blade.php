{{--
    Libreta digital en flotante.

    Un botón redondo encima del botón del chat, y un panel que carga /libreta en
    un iframe. Sin fondo difuminado: el resto de la página se sigue viendo y se
    puede usar.
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
        <div class="libreta-flotante-cabecera">
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
        /* column-reverse: el panel queda arriba y el boton abajo, pegado al
           ancla, que es justo encima del boton del chat. */
        flex-direction: column-reverse;
        align-items: flex-end;
        gap: 10px;
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

    /* Panel flotante, SIN fondo difuminado: se ve la página de detrás. */
    .libreta-flotante-panel {
        width: min(760px, calc(100vw - 44px));
        height: min(560px, calc(100vh - 160px));
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
    }

    .libreta-flotante-cabecera strong {
        flex: 1;
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
            height: min(520px, calc(100vh - 150px));
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

    if (!boton || !panel || !marco) return;

    const url = @json(url('/libreta') . '?flotante=1');

    const abrir = () => {
        // Se carga la primera vez y se reutiliza: así no se pierde lo escrito.
        if (marco.getAttribute('src') !== url) {
            marco.setAttribute('src', url);
        }

        panel.hidden = false;
        boton.setAttribute('aria-expanded', 'true');

        // Esc para cerrar
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
