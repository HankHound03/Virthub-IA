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

            /*
             * Equipo modesto: pocos nucleos, poca memoria o preferencia por menos
             * movimiento. Se apaga el desenfoque de fondo, que es lo que mas pesa
             * en la tarjeta grafica, y se acortan las animaciones.
             */
            try {
                var nucleos = navigator.hardwareConcurrency || 8;
                var memoria = navigator.deviceMemory || 8;
                var menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                /*
                 * Ahorro de datos o conexion lenta: tambien cuenta. Un equipo
                 * viejo suele ir acompañado de una conexion justa, y cargar menos
                 * cosas se agradece igual.
                 */
                var ahorroDatos = false;

                try {
                    ahorroDatos = !!(navigator.connection && navigator.connection.saveData);
                } catch (e) {
                    ahorroDatos = false;
                }

                if (nucleos <= 4 || memoria <= 4 || menosMovimiento || ahorroDatos) {
                    document.documentElement.classList.add('vh-ligero');
                }
            } catch (e) {
                /* Si el navegador no lo soporta, se queda el modo normal. */
            }
        })();
    </script>

    {{--
        La hoja del tema va como <link> normal, no creada por JavaScript.

        Antes se metía desde el script: el navegador ya estaba pintando cuando la
        descargaba, así que al recargar se veía un instante el diseño original
        antes del tema elegido. Como <link> en el <head>, entra en el primer
        pintado y ese parpadeo desaparece.

        La marca de tiempo evita que el navegador use una copia vieja.
    --}}
    <link id="vhTemasCss" rel="stylesheet"
          href="{{ asset('temas.css') }}?v={{ filemtime(public_path('temas.css')) }}">
@endif
