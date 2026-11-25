/*
 * Menu del perfil.
 *
 * Antes cada vista definia su propia copia; /libreta y /cambiar-password no la
 * tenian, asi que pulsar el avatar lanzaba un ReferenceError y no se podia ni
 * cerrar sesion desde ahi. Ahora esta en un solo archivo que carga la cabecera.
 */
(function () {
    'use strict';

    if (typeof window.toggleProfileMenu === 'function') {
        return;
    }

    window.toggleProfileMenu = function (evento) {
        if (evento && evento.stopPropagation) {
            evento.stopPropagation();
        }

        var menu = document.querySelector('.toggleable-profile-menu');

        if (menu) {
            menu.classList.toggle('is-open');
        }
    };

    // Un clic fuera lo cierra.
    document.addEventListener('click', function (evento) {
        var menu = document.querySelector('.toggleable-profile-menu');

        if (menu && menu.classList.contains('is-open') && ! menu.contains(evento.target)) {
            menu.classList.remove('is-open');
        }
    });
})();
