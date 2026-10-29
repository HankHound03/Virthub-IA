{{--
    AWTY · "¿Ya estamos ahí?"

    El mensaje que Francisco González dejó dentro del proyecto para quien lo
    encuentre. Se abre desde el botón AWTY de la cabecera.

    El texto está pensado para leerse: frases cortas, aire entre párrafos y nada
    de adornos que estorben. El sonido se carga solo al abrir, porque son casi
    6 minutos y no tiene sentido gastarlos en cada visita.
--}}
<div class="awty-overlay" id="awtyOverlay" hidden>
    <div class="awty-panel" role="dialog" aria-modal="true" aria-labelledby="awtyTitulo" aria-describedby="awtyTexto">
        <div class="awty-head">
            <span class="awty-title" id="awtyTitulo">AWTY · ¿Ya estamos ahí?</span>
            <button type="button" class="awty-close" id="awtyClose" aria-label="Cerrar">&times;</button>
        </div>

        <div class="awty-body" id="awtyTexto">
            <p>
                Hola. Si estás viendo los proyectos integradores y te encuentras con este proyecto,
                te agradecería que prestaras atención a los presentadores, pero sobre todo a este mensaje.
            </p>

            <p>
                Me llamo Francisco González y, al igual que tú, soy estudiante. Y lo fui, porque este
                proyecto se presenta previo a mis estadías finales.
            </p>

            <p>
                <strong>AWTY</strong> significa <em>are we there yet?</em> — en español, «¿ya estamos ahí?».
                Sabes cuándo entré a esta universidad —que con el paso del tiempo será mi alma máter—:
                sufrí demasiado. No soy un estudiante con mucho dinero; pagar colegiaturas fue difícil y,
                la verdad, más de una vez quise dejar la universidad. Sin embargo, mírame: aquí estoy.
            </p>

            <p>
                La pregunta nace de la impaciencia por llegar a un lugar. No seas impaciente.
                Créeme: esto es un proceso.
            </p>

            <p>
                Este proyecto fue hecho para estudiantes como yo, que no tienen dinero suficiente para
                cambiar su equipo de cómputo y necesitan herramientas demandantes de la carrera; que
                necesitan equipos bastante potentes para sus prácticas.
            </p>

            <p>
                Espero que este proyecto no sea olvidado con el tiempo y que algún día yo mismo lo
                retome, porque no es solo para hoy: lleva la filosofía de Linux,
                «de la comunidad para la comunidad».
            </p>

            <p class="awty-firma">
                Atentamente,<br>
                Un estudiante más, un friki, un alumno, un maestro, un ser humano más…<br>
                <strong>Francisco González</strong>
            </p>
        </div>

        <div class="awty-audio">
            <button type="button" class="awty-play" id="awtyPlay">
                <span class="awty-play-icono" aria-hidden="true">▶</span>
                <span id="awtyPlayTexto">Reproducir</span>
            </button>

            <button type="button" class="awty-stop" id="awtyStop" title="Detener y volver al inicio" aria-label="Detener">■</button>

            <span class="awty-tiempo" id="awtyTiempo">0:00 / 5:43</span>

            {{-- preload="none": los 4 MB solo se descargan si se pulsa reproducir. --}}
            <audio id="awtyAudio" preload="none" src="{{ asset('sounds/awty.mp3') }}"></audio>
        </div>
    </div>
</div>

<script>
    (function () {
        const boton = document.getElementById('awtyBtn');
        const overlay = document.getElementById('awtyOverlay');
        const cerrar = document.getElementById('awtyClose');
        const audio = document.getElementById('awtyAudio');
        const play = document.getElementById('awtyPlay');
        const playTexto = document.getElementById('awtyPlayTexto');
        const playIcono = play ? play.querySelector('.awty-play-icono') : null;
        const stop = document.getElementById('awtyStop');
        const tiempo = document.getElementById('awtyTiempo');

        if (!boton || !overlay || !audio) {
            return;
        }

        function etiqueta() {
            const segundos = Math.floor(audio.currentTime || 0);
            const minutos = Math.floor(segundos / 60);
            const resto = String(segundos % 60).padStart(2, '0');

            if (tiempo) {
                tiempo.textContent = minutos + ':' + resto + ' / 5:43';
            }
        }

        function mostrarIcono(pausado) {
            if (playIcono) playIcono.textContent = pausado ? '▶' : '❚❚';
            if (playTexto) playTexto.textContent = pausado ? 'Reproducir' : 'Pausar';
        }

        function abrir() {
            overlay.hidden = false;
            document.body.classList.add('awty-abierto');
            if (cerrar) cerrar.focus();
            etiqueta();
        }

        function cerrarPanel() {
            overlay.hidden = true;
            document.body.classList.remove('awty-abierto');

            // Al cerrar se pausa: si no, la musica sigue por detras.
            if (!audio.paused) {
                audio.pause();
                mostrarIcono(true);
            }
        }

        boton.addEventListener('click', abrir);
        if (cerrar) cerrar.addEventListener('click', cerrarPanel);

        overlay.addEventListener('click', (evento) => {
            if (evento.target === overlay) cerrarPanel();
        });

        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && !overlay.hidden) cerrarPanel();
        });

        play.addEventListener('click', () => {
            if (audio.paused) {
                audio.play().then(() => mostrarIcono(false)).catch(() => {});
            } else {
                audio.pause();
                mostrarIcono(true);
            }
        });

        if (stop) {
            stop.addEventListener('click', () => {
                audio.pause();
                audio.currentTime = 0;
                mostrarIcono(true);
                etiqueta();
            });
        }

        audio.addEventListener('timeupdate', etiqueta);
        audio.addEventListener('ended', () => {
            mostrarIcono(true);
            etiqueta();
        });
        audio.addEventListener('pause', () => mostrarIcono(true));
        audio.addEventListener('play', () => mostrarIcono(false));
    })();
</script>
