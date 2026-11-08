/*
 * Auditor de contraste para Virthub.
 *
 * Recorre la pagina y busca texto que no se lee: calcula el contraste entre el
 * color de cada texto y el fondo real que tiene detras (subiendo por los
 * ancestros hasta encontrar uno opaco). Lo que no llega a un minimo se pinta en
 * una lista fija arriba a la izquierda, para poder revisarlo en una captura.
 *
 * No forma parte de la aplicacion: solo se usa para revisar los temas.
 */
(function () {
    'use strict';

    /*
     * Se espera a que la pagina y sus hojas de estilo esten cargadas: medido
     * antes, el navegador todavia tiene los colores base y los numeros no valen.
     */
    function arrancar() {
        setTimeout(auditar, 350);
    }

    if (document.readyState === 'complete') {
        arrancar();
    } else {
        window.addEventListener('load', arrancar);
    }

    function auditar() {

    var MINIMO = 2.2;

    function aRgb(texto) {
        var m = String(texto).match(/rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?\)/);

        if (!m) {
            return null;
        }

        return {
            r: parseFloat(m[1]),
            g: parseFloat(m[2]),
            b: parseFloat(m[3]),
            a: m[4] === undefined ? 1 : parseFloat(m[4])
        };
    }

    function luminancia(c) {
        var s = [c.r, c.g, c.b].map(function (v) {
            v = v / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });

        return 0.2126 * s[0] + 0.7152 * s[1] + 0.0722 * s[2];
    }

    function contraste(a, b) {
        var la = luminancia(a);
        var lb = luminancia(b);

        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    function fondoDe(el) {
        var nodo = el;

        while (nodo && nodo !== document.documentElement) {
            var estilo = getComputedStyle(nodo);
            var color = aRgb(estilo.backgroundColor);

            /*
             * Los paneles del tema se pintan con un degradado plano del mismo color
             * (linear-gradient(panel, panel)). En ese caso el primer color del
             * degradado es el fondo real, y es lo que hay que medir.
             */
            var imagen = estilo.backgroundImage || 'none';

            if (imagen.indexOf('linear-gradient') !== -1) {
                var parada = aRgb(imagen);

                if (parada && parada.a >= 0.55) {
                    return parada;
                }
            }

            if (color && color.a >= 0.55) {
                return color;
            }

            // Papel pintado u otra imagen: no se puede medir, se salta.
            if (imagen !== 'none') {
                return null;
            }

            nodo = nodo.parentElement;
        }

        // Sin fondo opaco: se supone el fondo de la pagina (el papel pintado suele ser claro).
        var cuerpo = aRgb(getComputedStyle(document.body).backgroundColor);

        return cuerpo && cuerpo.a >= 0.5 ? cuerpo : { r: 240, g: 240, b: 240, a: 1 };
    }

    function ruta(el) {
        var partes = [];
        var nodo = el;

        for (var i = 0; i < 4 && nodo && nodo.tagName; i++) {
            var trozo = nodo.tagName.toLowerCase();

            if (nodo.id) {
                trozo += '#' + nodo.id;
            } else if (nodo.className && typeof nodo.className === 'string') {
                trozo += '.' + nodo.className.trim().split(/\s+/).slice(0, 2).join('.');
            }

            partes.unshift(trozo);
            nodo = nodo.parentElement;
        }

        return partes.join(' > ');
    }

    function visible(el) {
        var estilo = getComputedStyle(el);

        if (estilo.display === 'none' || estilo.visibility === 'hidden' || parseFloat(estilo.opacity) < 0.3) {
            return false;
        }

        var caja = el.getBoundingClientRect();

        return caja.width > 2 && caja.height > 2;
    }

    var problemas = [];
    var vistos = {};

    Array.prototype.forEach.call(document.querySelectorAll('body *'), function (el) {
        if (!visible(el)) {
            return;
        }

        // Solo elementos que tienen texto propio
        var texto = '';

        Array.prototype.forEach.call(el.childNodes, function (n) {
            if (n.nodeType === 3) {
                texto += n.nodeValue;
            }
        });

        texto = texto.trim();

        if (texto.length < 2) {
            return;
        }

        var estilo = getComputedStyle(el);
        var color = aRgb(estilo.color);

        if (!color) {
            return;
        }

        color.a = 1;

        var fondo = fondoDe(el);

        if (!fondo) {
            return;
        }

        var razon = contraste(color, fondo);

        if (razon >= MINIMO) {
            return;
        }

        var clave = ruta(el) + '|' + estilo.color;

        if (vistos[clave]) {
            return;
        }

        vistos[clave] = true;

        problemas.push({
            razon: razon,
            ruta: ruta(el),
            color: estilo.color,
            fondo: 'rgb(' + Math.round(fondo.r) + ',' + Math.round(fondo.g) + ',' + Math.round(fondo.b) + ')',
            texto: texto.slice(0, 32)
        });
    });

    problemas.sort(function (a, b) { return a.razon - b.razon; });

    var caja = document.createElement('pre');
    caja.style.cssText = 'position:fixed;top:0;left:0;z-index:999999;margin:0;padding:8px;' +
        'max-width:100vw;max-height:60vh;overflow:hidden;background:#000;color:#0f0;' +
        'font:10px/1.35 Consolas,monospace;white-space:pre-wrap;';

    var cabecera = 'TEMA ' + document.documentElement.dataset.era + '/' +
        document.documentElement.dataset.variante + ' · ' + location.pathname +
        ' · problemas: ' + problemas.length + '\n';

    caja.textContent = cabecera + problemas.slice(0, 18).map(function (p) {
        return p.razon.toFixed(2) + '  ' + p.color + ' sobre ' + p.fondo + '  ' + p.ruta + '  "' + p.texto + '"';
    }).join('\n');

    document.body.appendChild(caja);
    }
}());
