{{--
    La libreta, en un parcial para usarla en dos sitios:

      - la pagina completa (/libreta)
      - dentro del panel flotante del contenedor

    Asi las dos son el MISMO documento: comparten tema, estilos y codigo, sin
    iframe de por medio (que duplicaba el tema y la cabecera).
--}}

@php
    $usuarioLibreta = (string) ($currentUser['username'] ?? '');
    $esRegistrado = !empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest');

    $flotante = $flotante ?? false;

    if (!isset($notas)) {
        $notas = $esRegistrado && $usuarioLibreta !== ''
            ? app(\App\Services\NoteStore::class)->all($usuarioLibreta)
            : [];
    }
@endphp

<style>
        /* ------------------------------------------------------------------
           LIBRETA DIGITAL
           Dos columnas: la lista de notas y la nota abierta. Cuando la libreta
           va dentro del flotante, la cabecera del sitio no se pinta y el alto
           se ajusta al panel.
           ------------------------------------------------------------------ */

        body.libreta-cuerpo {
            margin: 0;
            background: transparent;
        }

        body.libreta-cuerpo.con-flotante {
            background-color: var(--vh-surface-strong);
        }

        .libreta {
            display: grid;
            grid-template-columns: minmax(210px, 260px) minmax(0, 1fr);
            gap: 10px;
            padding: 10px;
            min-height: calc(100vh - 150px);
            color: var(--vh-panel-text);
            font-family: Monocraft Nerd Font, monospace;
        }

        /* Dentro del panel flotante el alto es el del panel: si no, el contenido
           empuja y el final de la nota queda cortado. */
        .libreta-en-panel {
            min-height: 0;
            height: 100%;
            max-height: 100%;
            padding: 6px;
            grid-template-columns: minmax(140px, 175px) minmax(0, 1fr);
            overflow: hidden;
        }

        .libreta-lista,
        .libreta-editor {
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            border-radius: var(--vh-btn-radius, 8px);
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .libreta-cabecera {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px;
            border-bottom: 1px solid var(--vh-border);
        }

        .libreta-cabecera h1 {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .libreta-buscador {
            margin: 8px;
            padding: 5px 8px;
            border: 1px solid var(--vh-input-border, var(--vh-border));
            border-radius: var(--vh-btn-radius, 6px);
            background: var(--vh-input-bg, rgba(0, 0, 0, 0.18));
            color: var(--vh-input-text, var(--vh-text));
            font: inherit;
            font-size: 11.5px;
        }

        .libreta-notas {
            list-style: none;
            margin: 0;
            padding: 0 6px 8px;
            overflow-y: auto;
            flex: 1;
        }

        .libreta-nota {
            padding: 6px 8px;
            margin-bottom: 4px;
            border: 1px solid transparent;
            border-radius: var(--vh-btn-radius, 6px);
            cursor: pointer;
        }

        .libreta-nota:hover {
            background: var(--vh-btn-hover-bg, rgba(255, 255, 255, 0.08));
        }

        .libreta-nota.activa {
            border-color: var(--vh-accent, var(--vh-border));
            background: var(--vh-btn-hover-bg, rgba(255, 255, 255, 0.1));
        }

        .libreta-nota strong {
            display: block;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .libreta-nota small {
            color: var(--vh-text-soft);
            font-size: 10px;
        }

        .libreta-vacia {
            color: var(--vh-text-soft);
            font-size: 11.5px;
            padding: 12px 8px;
            text-align: center;
        }

        .libreta-boton {
            padding: 5px 10px;
            border: 1px solid var(--vh-btn-border, var(--vh-border));
            border-radius: var(--vh-btn-radius, 6px);
            background: var(--vh-btn-bg);
            color: var(--vh-btn-text, var(--vh-text));
            font: inherit;
            font-size: 11.5px;
            cursor: pointer;
            white-space: nowrap;
        }

        .libreta-boton:hover {
            background: var(--vh-btn-hover-bg, var(--vh-btn-bg));
            color: var(--vh-btn-hover-text, var(--vh-btn-text, var(--vh-text)));
        }

        .libreta-boton.peligro {
            color: var(--vh-alert-error, #d9534f);
        }

        .libreta-titulo {
            margin: 8px;
            padding: 6px 8px;
            border: 1px solid transparent;
            border-bottom-color: var(--vh-border);
            background: transparent;
            color: var(--vh-text);
            font: inherit;
            font-size: 15px;
            font-weight: 600;
        }

        .libreta-titulo:focus {
            outline: none;
            border-color: var(--vh-accent, var(--vh-border));
            border-radius: var(--vh-btn-radius, 6px);
        }

        .libreta-herramientas {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            padding: 0 8px 6px;
        }

        .libreta-herramientas button {
            padding: 3px 8px;
            border: 1px solid var(--vh-border);
            border-radius: 6px;
            background: transparent;
            color: var(--vh-text-soft);
            font: inherit;
            font-size: 11px;
            cursor: pointer;
        }

        .libreta-herramientas button:hover {
            background: var(--vh-btn-hover-bg, rgba(255, 255, 255, 0.1));
            color: var(--vh-text);
        }

        .libreta-paneles {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 0 8px 8px;
            flex: 1;
            min-height: 0;
        }

        .libreta-en-panel .libreta-paneles {
            grid-template-columns: 1fr;
        }

        .libreta-paneles.solo-editor {
            grid-template-columns: 1fr;
        }

        .libreta-paneles.solo-editor .libreta-vista {
            display: none;
        }

        .libreta-paneles.solo-vista .libreta-texto {
            display: none;
        }

        .libreta-texto {
            width: 100%;
            height: 100%;
            min-height: 240px;
            box-sizing: border-box;
            padding: 10px;
            border: 1px solid var(--vh-input-border, var(--vh-border));
            border-radius: var(--vh-btn-radius, 6px);
            background: var(--vh-input-bg, rgba(0, 0, 0, 0.18));
            color: var(--vh-input-text, var(--vh-text));
            font-family: Monocraft Nerd Font, monospace;
            font-size: 12.5px;
            line-height: 1.55;
            resize: none;
        }

        .libreta-texto:focus {
            outline: none;
            border-color: var(--vh-accent, var(--vh-border));
        }

        .libreta-vista {
            overflow-y: auto;
            padding: 10px 12px;
            border: 1px solid var(--vh-border);
            border-radius: var(--vh-btn-radius, 6px);
            background: var(--vh-surface-strong, rgba(0, 0, 0, 0.16));
            font-size: 12.5px;
            line-height: 1.6;
        }

        .libreta-vista h1,
        .libreta-vista h2,
        .libreta-vista h3 {
            margin: 12px 0 6px;
            color: var(--vh-text);
        }

        .libreta-vista h1 { font-size: 18px; }
        .libreta-vista h2 { font-size: 15px; }
        .libreta-vista h3 { font-size: 13px; }

        .libreta-vista p { margin: 0 0 8px; }

        .libreta-vista ul,
        .libreta-vista ol {
            margin: 0 0 8px;
            padding-left: 20px;
        }

        .libreta-vista code {
            padding: 1px 4px;
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.28);
            font-size: 11.5px;
        }

        .libreta-vista pre {
            padding: 8px;
            overflow-x: auto;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.32);
        }

        .libreta-vista pre code {
            background: none;
            padding: 0;
        }

        .libreta-vista blockquote {
            margin: 0 0 8px;
            padding-left: 10px;
            border-left: 3px solid var(--vh-accent, var(--vh-border));
            color: var(--vh-text-soft);
        }

        .libreta-vista a {
            color: var(--vh-accent, #6ea8ff);
        }

        .libreta-vista hr {
            border: none;
            border-top: 1px solid var(--vh-border);
            margin: 10px 0;
        }

        .libreta-vista .tarea {
            list-style: none;
            margin-left: -16px;
        }

        .libreta-pie {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 8px 8px;
            font-size: 10.5px;
            color: var(--vh-text-soft);
        }

        .libreta-pie .estado {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 720px) {
            .libreta {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
            }

            .libreta-lista {
                max-height: 160px;
            }

            .libreta-paneles {
                grid-template-columns: 1fr;
            }
        }
    </style>

<main class="libreta {{ $flotante ? 'libreta-en-panel' : '' }}" id="libreta"
      data-flotante="{{ $flotante ? '1' : '0' }}"
      data-url-nueva="{{ url('/libreta') }}"
      data-url-nota="{{ url('/libreta') }}"
      data-registrado="{{ !empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest') ? '1' : '0' }}">

    <section class="libreta-lista" aria-label="Notas">
        <div class="libreta-cabecera">
            <h1>Libreta</h1>
            <button type="button" class="libreta-boton" id="libretaNueva" title="Nueva nota">+ Nota</button>
        </div>

        <input type="search" id="libretaBuscar" class="libreta-buscador" placeholder="Buscar en la libreta…" autocomplete="off">

        <ul class="libreta-notas" id="libretaNotas">
            @forelse ($notas as $nota)
                <li class="libreta-nota" data-id="{{ $nota['id'] }}"
                    data-titulo="{{ $nota['title'] }}"
                    data-cuerpo="{{ $nota['body'] }}"
                    data-actualizada="{{ $nota['updated_at'] }}">
                    <strong>{{ $nota['title'] }}</strong>
                    <small>{{ \Illuminate\Support\Carbon::parse($nota['updated_at'])->format('d/m/Y H:i') }}</small>
                </li>
            @empty
                <li class="libreta-vacia" id="libretaVacia">Todavía no hay notas. Pulsa «+ Nota».</li>
            @endforelse
        </ul>
    </section>

    <section class="libreta-editor" aria-label="Nota abierta">
        <div class="libreta-cabecera">
            <h1 id="libretaTituloCabecera">Sin nota abierta</h1>
            <button type="button" class="libreta-boton peligro" id="libretaBorrar" title="Borrar esta nota" hidden>Borrar</button>
            <button type="button" class="libreta-boton" id="libretaGuardar" title="Guardar (Ctrl+S)">Guardar</button>
        </div>

        <input type="text" class="libreta-titulo" id="libretaTitulo" placeholder="Título de la nota" maxlength="120" disabled>

        <div class="libreta-herramientas" aria-label="Formato">
            <button type="button" data-formato="**|**" title="Negrita">B</button>
            <button type="button" data-formato="*|*" title="Cursiva"><em>I</em></button>
            <button type="button" data-formato="## " title="Título">H2</button>
            <button type="button" data-formato="- " title="Lista">•</button>
            <button type="button" data-formato="1. " title="Lista numerada">1.</button>
            <button type="button" data-formato="- [ ] " title="Tarea">☐</button>
            <button type="button" data-formato="`` | ``" title="Código">`</button>
            <button type="button" data-formato="> " title="Cita">❝</button>
            <button type="button" data-formato="[texto](url)" title="Enlace">🔗</button>
            <button type="button" data-formato="---" title="Separador">―</button>
            <button type="button" id="libretaAlternar" title="Solo editor / solo vista">👁</button>
        </div>

        <div class="libreta-paneles" id="libretaPaneles">
            <textarea class="libreta-texto" id="libretaTexto" placeholder="# Escribe en markdown…" disabled></textarea>
            <div class="libreta-vista" id="libretaVista" aria-live="polite"></div>
        </div>

        <div class="libreta-pie">
            <span class="estado" id="libretaEstado"></span>
            <span id="libretaCuenta">0 palabras</span>
        </div>
    </section>
</main>

<script>
(() => {
    'use strict';

    const raiz = document.getElementById('libreta');
    if (!raiz) return;

    const lista = document.getElementById('libretaNotas');
    const buscador = document.getElementById('libretaBuscar');
    const titulo = document.getElementById('libretaTitulo');
    const texto = document.getElementById('libretaTexto');
    const vista = document.getElementById('libretaVista');
    const paneles = document.getElementById('libretaPaneles');
    const cabecera = document.getElementById('libretaTituloCabecera');
    const botonNueva = document.getElementById('libretaNueva');
    const botonGuardar = document.getElementById('libretaGuardar');
    const botonBorrar = document.getElementById('libretaBorrar');
    const estado = document.getElementById('libretaEstado');
    const cuenta = document.getElementById('libretaCuenta');
    const registrado = raiz.dataset.registrado === '1';

    let notaActual = null;
    let guardadoPendiente = null;

    const csrf = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    const avisar = (mensaje) => {
        estado.textContent = mensaje;
    };

    // -----------------------------------------------------------------
    // Markdown
    // -----------------------------------------------------------------

    const escapar = (cadena) => cadena
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const formatearLinea = (linea) => {
        let salida = escapar(linea);

        // Codigo en linea
        salida = salida.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Negrita e italica
        salida = salida.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        salida = salida.replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>');
        // Enlaces
        salida = salida.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

        return salida;
    };

    const render = (markdown) => {
        const lineas = String(markdown || '').split('\n');
        const salida = [];
        let enLista = false;
        let enCodigo = false;

        const cerrarLista = () => {
            if (enLista) {
                salida.push('</ul>');
                enLista = false;
            }
        };

        lineas.forEach((linea) => {
            // Bloque de codigo ```
            if (/^```/.test(linea)) {
                cerrarLista();
                if (enCodigo) {
                    salida.push('</code></pre>');
                } else {
                    salida.push('<pre><code>');
                }
                enCodigo = !enCodigo;
                return;
            }

            if (enCodigo) {
                salida.push(escapar(linea));
                return;
            }

            if (/^\s*$/.test(linea)) {
                cerrarLista();
                return;
            }

            // Separador
            if (/^---+$/.test(linea.trim())) {
                cerrarLista();
                salida.push('<hr>');
                return;
            }

            // Titulos
            const titulo = linea.match(/^(#{1,3})\s+(.*)$/);
            if (titulo) {
                cerrarLista();
                const nivel = titulo[1].length;
                salida.push('<h' + nivel + '>' + formatearLinea(titulo[2]) + '</h' + nivel + '>');
                return;
            }

            // Cita
            if (/^>\s?/.test(linea)) {
                cerrarLista();
                salida.push('<blockquote>' + formatearLinea(linea.replace(/^>\s?/, '')) + '</blockquote>');
                return;
            }

            // Tarea o vineta
            const tarea = linea.match(/^\s*[-*]\s+\[( |x|X)\]\s+(.*)$/);
            if (tarea) {
                if (!enLista) {
                    salida.push('<ul>');
                    enLista = true;
                }
                const hecho = tarea[1].toLowerCase() === 'x' ? ' checked' : '';
                salida.push('<li class="tarea"><input type="checkbox" disabled' + hecho + '> ' + formatearLinea(tarea[2]) + '</li>');
                return;
            }

            const vineta = linea.match(/^\s*[-*]\s+(.*)$/);
            if (vineta) {
                if (!enLista) {
                    salida.push('<ul>');
                    enLista = true;
                }
                salida.push('<li>' + formatearLinea(vineta[1]) + '</li>');
                return;
            }

            const numerada = linea.match(/^\s*\d+\.\s+(.*)$/);
            if (numerada) {
                if (!enLista) {
                    salida.push('<ul>');
                    enLista = true;
                }
                salida.push('<li>' + formatearLinea(numerada[1]) + '</li>');
                return;
            }

            cerrarLista();
            salida.push('<p>' + formatearLinea(linea) + '</p>');
        });

        if (enCodigo) {
            salida.push('</code></pre>');
        }

        cerrarLista();

        return salida.join('\n') || '<p style="color:var(--vh-text-soft)">Escribe algo y aparecerá aquí.</p>';
    };

    // -----------------------------------------------------------------
    // Estado
    // -----------------------------------------------------------------

    const contarPalabras = () => {
        const palabras = (texto.value.trim().match(/\S+/g) || []).length;
        cuenta.textContent = palabras + (palabras === 1 ? ' palabra' : ' palabras');
    };

    const actualizarVista = () => {
        vista.innerHTML = render(texto.value);
        contarPalabras();
    };

    const marcarActiva = () => {
        Array.from(lista.querySelectorAll('.libreta-nota')).forEach((elemento) => {
            elemento.classList.toggle('activa', elemento.dataset.id === notaActual);
        });
    };

    const abrir = (elementoNota) => {
        if (!elementoNota) {
            notaActual = null;
            titulo.value = '';
            texto.value = '';
            titulo.disabled = true;
            texto.disabled = true;
            botonBorrar.hidden = true;
            cabecera.textContent = 'Sin nota abierta';
            avisar('');
            actualizarVista();
            marcarActiva();
            return;
        }

        notaActual = elementoNota.dataset.id;
        titulo.value = elementoNota.dataset.titulo || '';
        texto.value = elementoNota.dataset.cuerpo || '';
        titulo.disabled = false;
        texto.disabled = false;
        botonBorrar.hidden = false;
        cabecera.textContent = titulo.value || 'Sin título';
        avisar('Guardado ' + (elementoNota.dataset.actualizada || '').slice(11, 16));
        actualizarVista();
        marcarActiva();
    };

    // -----------------------------------------------------------------
    // Servidor
    // -----------------------------------------------------------------

    const enviar = async (url, datos) => {
        const respuesta = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(datos)
        });

        const cuerpo = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
            throw new Error(cuerpo.error || 'No se pudo guardar.');
        }

        return cuerpo;
    };

    const crearElemento = (nota) => {
        const elemento = document.createElement('li');
        elemento.className = 'libreta-nota';
        elemento.dataset.id = nota.id;
        elemento.dataset.titulo = nota.title;
        elemento.dataset.cuerpo = nota.body;
        elemento.dataset.actualizada = nota.updated_at;

        const fuerte = document.createElement('strong');
        fuerte.textContent = nota.title;
        elemento.appendChild(fuerte);

        const pequena = document.createElement('small');
        pequena.textContent = 'ahora';
        elemento.appendChild(pequena);

        elemento.addEventListener('click', () => abrir(elemento));

        return elemento;
    };

    const guardar = async (silencioso = false) => {
        if (!notaActual) return;

        const elemento = lista.querySelector('.libreta-nota[data-id="' + notaActual + '"]');
        if (!elemento) return;

        avisar('Guardando…');

        try {
            const datos = await enviar(raiz.dataset.urlNota + '/' + notaActual, {
                title: titulo.value,
                body: texto.value
            });

            elemento.dataset.titulo = datos.note.title;
            elemento.dataset.cuerpo = datos.note.body;
            elemento.dataset.actualizada = datos.note.updated_at;
            elemento.querySelector('strong').textContent = datos.note.title;
            cabecera.textContent = datos.note.title;

            avisar('Guardado ' + datos.note.updated_at.slice(11, 16));

            if (!silencioso) {
                return;
            }
        } catch (error) {
            avisar(error.message);
        }
    };

    // -----------------------------------------------------------------
    // Eventos
    // -----------------------------------------------------------------

    titulo.addEventListener('input', () => {
        cabecera.textContent = titulo.value || 'Sin título';
    });

    texto.addEventListener('input', () => {
        actualizarVista();
        clearTimeout(guardadoPendiente);
        guardadoPendiente = setTimeout(() => guardar(true), 1200);
    });

    titulo.addEventListener('blur', () => guardar(true));
    texto.addEventListener('blur', () => guardar(true));

    document.addEventListener('keydown', (evento) => {
        if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 's') {
            evento.preventDefault();
            guardar();
        }
    });

    botonGuardar.addEventListener('click', () => guardar());
    botonBorrar.addEventListener('click', () => borrar());

    async function borrar() {
        if (!notaActual) return;

        const elemento = lista.querySelector('.libreta-nota[data-id="' + notaActual + '"]');

        if (!elemento) return;

        const pregunta = window.vhConfirmar
            ? await window.vhConfirmar({
                titulo: 'Borrar nota',
                mensaje: 'Se va a borrar «' + (elemento.dataset.titulo || 'esta nota') + '». No se puede deshacer.',
                boton: 'Borrar'
            })
            : window.confirm('¿Borrar esta nota?');

        if (!pregunta) return;

        try {
            await enviar(raiz.dataset.urlNota + '/' + notaActual + '/borrar', {});
            elemento.remove();
            abrir(null);
            avisar('Nota borrada');

            if (!lista.querySelector('.libreta-nota')) {
                const vacia = document.createElement('li');
                vacia.className = 'libreta-vacia';
                vacia.textContent = 'Todavía no hay notas. Pulsa «+ Nota».';
                lista.appendChild(vacia);
            }
        } catch (error) {
            avisar(error.message);
        }
    }

    botonNueva.addEventListener('click', async () => {
        if (!registrado) {
            avisar('Inicia sesión para guardar notas.');
            return;
        }

        try {
            const datos = await enviar(raiz.dataset.urlNueva, { title: 'Nota sin título', body: '' });

            const vacia = lista.querySelector('.libreta-vacia');
            if (vacia) vacia.remove();

            const elemento = crearElemento(datos.note);
            lista.prepend(elemento);
            abrir(elemento);
            titulo.focus();
            titulo.select();
        } catch (error) {
            avisar(error.message);
        }
    });

    lista.addEventListener('click', (evento) => {
        const elemento = evento.target.closest('.libreta-nota');

        if (elemento) {
            abrir(elemento);
        }
    });

    Array.from(lista.querySelectorAll('.libreta-nota')).forEach((elemento) => {
        elemento.addEventListener('click', () => abrir(elemento));
    });

    buscador.addEventListener('input', () => {
        const consulta = buscador.value.trim().toLowerCase();

        Array.from(lista.querySelectorAll('.libreta-nota')).forEach((elemento) => {
            const contenido = (elemento.dataset.titulo + ' ' + elemento.dataset.cuerpo).toLowerCase();
            elemento.hidden = consulta !== '' && !contenido.includes(consulta);
        });
    });

    // Botones de formato: envuelven la selección o insertan al principio de la línea
    document.querySelectorAll('[data-formato]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const formato = boton.dataset.formato;

            if (!notaActual) return;

            const inicio = texto.selectionStart;
            const fin = texto.selectionEnd;
            const seleccion = texto.value.slice(inicio, fin);

            let nuevo;
            let cursor;

            if (formato.includes('|')) {
                const [antes, despues] = formato.split('|');
                nuevo = antes + seleccion + despues;
                cursor = inicio + antes.length + seleccion.length;
            } else if (formato === '[]') {
                nuevo = '[' + seleccion + '](url)';
                cursor = inicio + 1 + seleccion.length;
            } else if (formato.includes('texto')) {
                nuevo = seleccion ? '[texto](url)' : formato;
                cursor = inicio + nuevo.length;
            } else {
                // Prefijo de línea (## , - , > …)
                const inicioLinea = texto.value.lastIndexOf('\n', inicio - 1) + 1;
                nuevo = formato + seleccion;
                texto.value = texto.value.slice(0, inicioLinea) + nuevo + texto.value.slice(fin);
                texto.selectionStart = texto.selectionEnd = inicioLinea + nuevo.length;
                actualizarVista();
                return;
            }

            texto.value = texto.value.slice(0, inicio) + nuevo + texto.value.slice(fin);
            texto.selectionStart = texto.selectionEnd = cursor;
            texto.focus();
            actualizarVista();
        });
    });

    document.getElementById('libretaAlternar').addEventListener('click', () => {
        if (paneles.classList.contains('solo-editor')) {
            paneles.classList.remove('solo-editor');
            paneles.classList.add('solo-vista');
        } else if (paneles.classList.contains('solo-vista')) {
            paneles.classList.remove('solo-vista');
        } else {
            paneles.classList.add('solo-editor');
        }
    });

    // Primera nota abierta al entrar
    const primera = lista.querySelector('.libreta-nota');
    if (primera) {
        abrir(primera);
    } else {
        actualizarVista();
    }

    if (!registrado) {
        avisar('Inicia sesión para guardar notas.');
    }
})();
</script>
