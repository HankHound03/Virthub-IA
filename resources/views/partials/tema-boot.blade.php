{{--
    Arranque del tema, en el <head> y ANTES de pintar.

    Por que va aqui y no en el header: si el tema se aplica despues de que el
    navegador pinte, se ve durante unos milisegundos el diseno anterior. Poniendo
    el atributo y la hoja de estilos en el <head>, el navegador ya pinta con el
    tema elegido desde el primer fotograma.

    Se guarda en localStorage, asi que es cosa del navegador: el servidor no puede
    saberlo. De ahi que haya que resolverlo con este script en linea (que si se
    ejecuta antes del primer pintado) y no con codigo que espere a cargar.
--}}
@if (config('virthub.temas.enabled'))
    <script>
        (function () {
            var CLAVE = 'virthub-tema';
            var ERA = 'vista';
            var VARIANTE = 'aero';
            var guardado = null;

            try {
                guardado = JSON.parse(localStorage.getItem(CLAVE) || 'null');
            } catch (e) {
                guardado = null;
            }

            var era = guardado && guardado.era ? guardado.era : ERA;
            var variante = guardado && guardado.variante ? guardado.variante : VARIANTE;

            /* En <html>, no en <body>: aqui el body todavia no existe. */
            document.documentElement.setAttribute('data-era', era);
            document.documentElement.setAttribute('data-variante', variante);

            var enlace = document.createElement('link');
            enlace.rel = 'stylesheet';
            enlace.href = @json(asset('temas.css'));
            enlace.id = 'vhTemasCss';
            document.head.appendChild(enlace);
        })();
    </script>
@endif
