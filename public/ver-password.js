/*
 * Ver la contrasena mientras se escribe.
 *
 * Anade un boton dentro de cada campo de contrasena para mostrarla u ocultarla.
 * El texto NO se puede copiar: al revelarlo se bloquean copiar, cortar, el menu
 * del boton derecho, arrastrar y seleccionar. La idea es poder revisar lo que se
 * escribio, no sacarlo de la pagina.
 *
 * Se aplica sola a todos los campos type="password" de la pagina; no hay que
 * tocar cada formulario.
 */
(function () {
    'use strict';

    var OJO_ABIERTO =
        '<svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" ' +
        'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M1.6 10S4.8 4.6 10 4.6 18.4 10 18.4 10 15.2 15.4 10 15.4 1.6 10 1.6 10z"/>' +
        '<circle cx="10" cy="10" r="2.6"/></svg>';

    var OJO_TACHADO =
        '<svg viewBox="0 0 20 20" width="16" height="16" fill="none" stroke="currentColor" ' +
        'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M1.6 10S4.8 4.6 10 4.6 18.4 10 18.4 10 15.2 15.4 10 15.4 1.6 10 1.6 10z"/>' +
        '<circle cx="10" cy="10" r="2.6"/><path d="M3 17 17 3"/></svg>';

    /* Mientras esta a la vista, el texto no se puede sacar de la pagina. */
    function bloquearCopia(campo) {
        ['copy', 'cut', 'dragstart', 'contextmenu', 'selectstart'].forEach(function (evento) {
            campo.addEventListener(evento, function (e) {
                e.preventDefault();
            });
        });
    }

    function preparar(campo) {
        if (campo.dataset.verPasswordListo === '1') {
            return;
        }

        campo.dataset.verPasswordListo = '1';

        var contenedor = campo.parentElement;

        if (!contenedor) {
            return;
        }

        /*
         * Si el campo esta suelto dentro de un formulario (login, instalador), el
         * padre es el propio <form>: posicionar el boton ahi lo mandaria al borde
         * del formulario y no del campo. En ese caso se envuelve el campo en un
         * contenedor propio; si ya esta dentro de una caja (una etiqueta con su
         * campo), se usa esa.
         */
        var controles = contenedor.querySelectorAll('input, textarea, select');

        if (contenedor.tagName === 'FORM' || controles.length > 1) {
            var envoltorio = document.createElement('span');
            envoltorio.className = 'campo-con-ver';
            contenedor.insertBefore(envoltorio, campo);
            envoltorio.appendChild(campo);
            contenedor = envoltorio;
        } else {
            contenedor.classList.add('campo-con-ver');
        }

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'boton-ver-password';
        boton.setAttribute('aria-pressed', 'false');
        boton.setAttribute('aria-label', 'Ver la contraseña');
        boton.title = 'Ver la contraseña';
        boton.innerHTML = OJO_ABIERTO;

        boton.addEventListener('click', function (evento) {
            evento.preventDefault();
            evento.stopPropagation();

            var visible = campo.type === 'text';

            if (visible) {
                campo.type = 'password';
                campo.classList.remove('password-a-la-vista');
            } else {
                campo.type = 'text';
                campo.classList.add('password-a-la-vista');
            }

            boton.innerHTML = visible ? OJO_ABIERTO : OJO_TACHADO;
            boton.setAttribute('aria-pressed', visible ? 'false' : 'true');
            boton.setAttribute('aria-label', visible ? 'Ver la contraseña' : 'Ocultar la contraseña');
            boton.title = visible ? 'Ver la contraseña' : 'Ocultar la contraseña';

            campo.focus();
        });

        /* Al salir del campo vuelve a ocultarse: nadie deja una contrasena a la
           vista porque se fue a otra ventana. */
        campo.addEventListener('blur', function () {
            if (campo.type !== 'text') {
                return;
            }

            campo.type = 'password';
            campo.classList.remove('password-a-la-vista');
            boton.innerHTML = OJO_ABIERTO;
            boton.setAttribute('aria-pressed', 'false');
            boton.setAttribute('aria-label', 'Ver la contraseña');
            boton.title = 'Ver la contraseña';
        });

        bloquearCopia(campo);
        contenedor.appendChild(boton);
    }

    function iniciar() {
        Array.prototype.forEach.call(document.querySelectorAll('input[type="password"]'), preparar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    /* Campos que aparecen despues (por ejemplo al abrir una pestaña) tambien. */
    document.addEventListener('click', function () {
        window.setTimeout(iniciar, 60);
    }, true);
}());
