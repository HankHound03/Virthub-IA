/*
 * Confirmacion en la pagina, no en el dialogo del navegador.
 *
 * El confirm() del navegador no sigue el tema: aparece como una caja gris del
 * sistema encima de un escritorio de Windows XP o de Vista. Esto pinta la misma
 * pregunta con los colores del tema y los botones del resto de la aplicacion.
 *
 * Uso: en el formulario, un atributo con la pregunta.
 *
 *     <form method="POST" action="..." data-confirmar="¿Borrar esta publicación?">
 *
 * Opcional: data-confirmar-titulo="Borrar publicacion".
 *
 * Tambien se puede llamar desde JavaScript:
 *
 *     vhConfirmar({ titulo: 'Borrar', mensaje: '¿Seguro?' }).then(ok => { ... });
 */
(function () {
    'use strict';

    var caja = null;
    var resolver = null;

    function crear() {
        if (caja) {
            return caja;
        }

        caja = document.createElement('div');
        caja.className = 'vh-confirm';
        caja.hidden = true;
        caja.setAttribute('role', 'dialog');
        caja.setAttribute('aria-modal', 'true');
        caja.setAttribute('aria-labelledby', 'vhConfirmTitulo');

        caja.innerHTML =
            '<div class="vh-confirm-caja">' +
            '  <h3 id="vhConfirmTitulo" class="vh-confirm-titulo">Confirmar</h3>' +
            '  <p class="vh-confirm-mensaje"></p>' +
            '  <div class="vh-confirm-acciones">' +
            '    <button type="button" class="vh-confirm-cancelar">Cancelar</button>' +
            '    <button type="button" class="vh-confirm-aceptar">Confirmar</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(caja);

        var titulo = caja.querySelector('.vh-confirm-titulo');
        var mensaje = caja.querySelector('.vh-confirm-mensaje');
        var cancelar = caja.querySelector('.vh-confirm-cancelar');
        var aceptar = caja.querySelector('.vh-confirm-aceptar');

        function cerrar(valor) {
            caja.hidden = true;
            document.documentElement.style.overflow = '';

            if (resolver) {
                var r = resolver;
                resolver = null;
                r(valor);
            }
        }

        cancelar.addEventListener('click', function () {
            cerrar(false);
        });

        aceptar.addEventListener('click', function () {
            cerrar(true);
        });

        caja.addEventListener('click', function (evento) {
            // Un clic fuera de la caja cancela.
            if (evento.target === caja) {
                cerrar(false);
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (caja.hidden) {
                return;
            }

            if (evento.key === 'Escape') {
                evento.preventDefault();
                cerrar(false);
            }

            if (evento.key === 'Enter') {
                evento.preventDefault();
                cerrar(true);
            }
        });

        caja.abrir = function (opciones) {
            titulo.textContent = opciones.titulo || 'Confirmar';
            mensaje.textContent = opciones.mensaje || '¿Seguro?';
            aceptar.textContent = opciones.boton || 'Confirmar';

            caja.hidden = false;
            document.documentElement.style.overflow = 'hidden';

            window.setTimeout(function () {
                cancelar.focus();
            }, 30);

            return new Promise(function (res) {
                resolver = res;
            });
        };

        return caja;
    }

    window.vhConfirmar = function (opciones) {
        return crear().abrir(opciones || {});
    };

    /*
     * Cualquier formulario con data-confirmar pasa por aqui antes de enviarse.
     * Se intercepta en la fase de captura para llegar antes que otros manejadores.
     */
    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;

        if (!formulario || !formulario.hasAttribute) {
            return;
        }

        if (!formulario.hasAttribute('data-confirmar')) {
            return;
        }

        // Ya confirmado: dejamos pasar.
        if (formulario.dataset.confirmado === '1') {
            delete formulario.dataset.confirmado;
            return;
        }

        evento.preventDefault();
        evento.stopPropagation();

        window.vhConfirmar({
            titulo: formulario.getAttribute('data-confirmar-titulo') || 'Confirmar',
            mensaje: formulario.getAttribute('data-confirmar'),
            boton: formulario.getAttribute('data-confirmar-boton') || 'Confirmar'
        }).then(function (aceptado) {
            if (!aceptado) {
                return;
            }

            formulario.dataset.confirmado = '1';
            formulario.submit();
        });
    }, true);
}());
