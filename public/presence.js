/*
 * Presencia en el navegador, al estilo Windows Live Messenger.
 *
 * El anillo de la foto de perfil dice como esta la persona:
 *
 *   verde   en linea   la pestaña esta abierta y activa
 *   ambar   ausente    lleva un rato sin hacer nada (o la pestaña esta oculta)
 *   rojo    desconectado  no hay senal reciente
 *
 * Se manda un latido al servidor cada minuto mientras la pestaña esta visible.
 * Al ocultarla se deja de mandar, y el estado cae solo: primero a ausente y
 * luego a desconectado, con los mismos umbrales que usa el servidor.
 */
(function () {
    'use strict';

    var INTERVALO = 60000;      // un latido por minuto
    var AUSENTE_MS = 300000;    // 5 minutos sin actividad: ausente
    var DESCONECTADO_MS = 1800000; // 30 minutos: desconectado

    var ultimoLatido = 0;
    var ultimaActividad = Date.now();
    var estado = 'online';
    var temporizador = null;

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function pintar(nuevoEstado) {
        if (nuevoEstado === estado) {
            return;
        }

        estado = nuevoEstado;

        document.querySelectorAll('[data-presence-self]').forEach(function (elemento) {
            elemento.dataset.presence = nuevoEstado;
        });

        // Avisa por si otra parte de la pagina quiere reaccionar.
        document.dispatchEvent(new CustomEvent('vh:presencia', { detail: { estado: nuevoEstado } }));
    }

    function latir() {
        ultimoLatido = Date.now();

        if (document.hidden) {
            return;
        }

        fetch('/chat/presence', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ping_at: Date.now() })
        }).catch(function () {
            // Un latido fallido no cambia nada: el siguiente lo reintenta.
        });
    }

    function revisarEstado() {
        var ahora = Date.now();
        var quieto = ahora - ultimaActividad;

        if (document.hidden && quieto >= AUSENTE_MS) {
            pintar(quieto >= DESCONECTADO_MS ? 'offline' : 'away');
            return;
        }

        if (quieto >= DESCONECTADO_MS) {
            pintar('offline');
            return;
        }

        if (quieto >= AUSENTE_MS) {
            pintar('away');
            return;
        }

        pintar('online');
    }

    function marcarActividad() {
        ultimaActividad = Date.now();
        revisarEstado();
    }

    function iniciar() {
        if (!document.querySelector('[data-presence-self]')) {
            return;
        }

        pintar('online');
        latir();

        // Cualquier seña de vida cuenta como actividad.
        ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'focus'].forEach(function (evento) {
            document.addEventListener(evento, marcarActividad, { passive: true });
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                revisarEstado();
                return;
            }

            marcarActividad();
            latir();
        });

        temporizador = setInterval(function () {
            if (!document.hidden) {
                latir();
            }

            revisarEstado();
        }, INTERVALO);

    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
