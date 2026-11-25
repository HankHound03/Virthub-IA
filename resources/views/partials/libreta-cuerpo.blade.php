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

    /* Cuando la libreta viene dentro del contenedor no hay controlador que
       prepare los datos: se piden aqui. Las carpetas hay que pedirlas tambien,
       si no el flotante se quedaba sin materias. */
    if (!isset($notas) || !isset($carpetas)) {
        $almacen = $esRegistrado && $usuarioLibreta !== ''
            ? app(\App\Services\NoteStore::class)
            : null;

        $notas = $notas ?? ($almacen ? $almacen->all($usuarioLibreta) : []);
        $carpetas = $carpetas ?? ($almacen ? $almacen->folders($usuarioLibreta) : []);
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
            /* 100dvh y no 100vh: en el movil la barra del navegador aparece y
               desaparece, y con vh el alto queda descuadrado. */
            min-height: calc(100dvh - 150px);
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
            background-color: var(--vh-surface, #23282f);
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

        .libreta-carpetas {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 6px 6px 4px;
            overflow-y: auto;
        }

        /* En escritorio la lista de materias no debe comerse las notas; en el
           movil, recortarla hacia que se vieran filas a medias. */
        @media (min-width: 721px) {
            .libreta-carpetas {
                max-height: 34%;
            }
        }

        .libreta-lista-materias {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .libreta-carpeta {
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            padding: 4px 8px;
            border: 1px solid transparent;
            border-radius: var(--vh-btn-radius, 6px);
            background: transparent;
            color: var(--vh-text);
            font: inherit;
            font-size: 11.5px;
            text-align: left;
            cursor: pointer;
        }

        .libreta-carpeta:hover {
            background: var(--vh-btn-hover-bg, rgba(255, 255, 255, 0.08));
        }

        .libreta-carpeta.activa {
            border-color: var(--vh-accent, var(--vh-border));
            background: var(--vh-btn-hover-bg, rgba(255, 255, 255, 0.1));
        }

        .libreta-carpeta-nombre {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .libreta-cuenta {
            font-size: 10px;
            color: var(--vh-text-soft);
        }

        /* En pantallas sin raton no hay hover: la × se ve siempre. */
        @media (hover: none) {
            .libreta-carpeta-borrar {
                display: inline;
            }
        }

        .libreta-carpeta:focus-within .libreta-carpeta-borrar {
            display: inline;
        }

        /* La × para borrar la materia aparece al pasar por encima */
        .libreta-carpeta-borrar {
            display: none;
            padding: 0 3px;
            color: var(--vh-alert-error, #d9534f);
        }

        .libreta-carpeta:hover .libreta-carpeta-borrar {
            display: inline;
        }

        .libreta-carpeta-nueva {
            color: var(--vh-text-soft);
            border-style: dashed;
            border-color: var(--vh-border);
            justify-content: center;
        }

        .libreta-carpeta-form {
            display: flex;
            gap: 4px;
            padding: 2px;
        }

        .libreta-carpeta-form[hidden] {
            display: none;
        }

        .libreta-carpeta-form input {
            flex: 1;
            min-width: 0;
            padding: 4px 6px;
            border: 1px solid var(--vh-input-border, var(--vh-border));
            border-radius: 6px;
            background: var(--vh-input-bg, rgba(0, 0, 0, 0.18));
            color: var(--vh-input-text, var(--vh-text));
            font: inherit;
            font-size: 11.5px;
        }

        .libreta-selector {
            max-width: 150px;
            padding: 4px 6px;
            border: 1px solid var(--vh-border);
            border-radius: var(--vh-btn-radius, 6px);
            background: var(--vh-input-bg, rgba(0, 0, 0, 0.18));
            color: var(--vh-input-text, var(--vh-text));
            font: inherit;
            font-size: 11.5px;
        }

        /* Materia de cada nota en la lista */
        .libreta-nota .libreta-nota-carpeta {
            display: inline-block;
            margin-top: 2px;
            padding: 0 6px;
            border: 1px solid var(--vh-border);
            border-radius: 999px;
            font-size: 9.5px;
            color: var(--vh-text-soft);
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

        /* ------------------------------------------------------------------
           MOVIL: una sola columna y sin altos fijos

           Antes la lista se quedaba en 160 px con overflow oculto y el editor
           se montaba encima: se veian filas cortadas y tarjetas solapadas.
           Ahora cada bloque ocupa lo que necesita y la lista de notas tiene su
           propio desplazamiento.
           ------------------------------------------------------------------ */
        @media (max-width: 820px) {
            body.libreta-cuerpo {
                overflow-y: auto;
            }

            .libreta {
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: auto auto;
                min-height: 0;
                padding: 8px;
                gap: 8px;
            }

            .libreta-lista {
                max-height: none;
            }

            .libreta-notas {
                max-height: 38dvh;
            }

            .libreta-editor {
                min-height: 72dvh;
            }

            .libreta-paneles {
                grid-template-columns: minmax(0, 1fr);
            }

            .libreta-texto {
                min-height: 34dvh;
            }

            .libreta-vista {
                min-height: 20dvh;
                max-height: 40dvh;
            }

            /* La cabecera del editor cabe en dos filas si hace falta. */
            .libreta-editor > .libreta-cabecera {
                flex-wrap: wrap;
            }

            .libreta-selector {
                max-width: none;
                flex: 1 1 120px;
            }

            /* Botones de formato: que no se salgan del ancho. */
            .libreta-herramientas {
                flex-wrap: wrap;
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

        {{-- Materias: cada carpeta agrupa sus notas. --}}
        <div class="libreta-carpetas" id="libretaCarpetas">
            {{-- Esta lista la vuelve a pintar JavaScript cuando se crea, renombra
                 o borra una materia, sin recargar la pagina. --}}
            <div class="libreta-lista-materias" id="libretaListaMaterias"></div>

            <button type="button" class="libreta-carpeta libreta-carpeta-nueva" id="libretaNuevaCarpeta" title="Crear una materia">
                + Materia
            </button>

            <script type="application/json" id="libretaDatos">
                @json(['carpetas' => array_values($carpetas ?? []), 'activa' => '*'])
            </script>

            <div class="libreta-carpeta-form" id="libretaCarpetaForm" hidden>
                <input type="text" id="libretaCarpetaNombre" maxlength="60" placeholder="Nombre de la materia" autocomplete="off">
                <button type="button" id="libretaCarpetaGuardar" class="libreta-boton">Crear</button>
            </div>
        </div>

        <input type="search" id="libretaBuscar" class="libreta-buscador" placeholder="Buscar en la libreta…" autocomplete="off">

        <ul class="libreta-notas" id="libretaNotas">
            @forelse ($notas as $nota)
                <li class="libreta-nota" data-id="{{ $nota['id'] }}"
                    data-titulo="{{ $nota['title'] }}"
                    data-cuerpo="{{ $nota['body'] }}"
                    data-carpeta="{{ $nota['folder'] ?? '' }}"
                    data-actualizada="{{ $nota['updated_at'] }}">
                    <strong>{{ $nota['title'] }}</strong>
                    <small>{{ \Illuminate\Support\Carbon::parse($nota['updated_at'])->format('d/m/Y H:i') }}</small>
                    @if (!empty($nota['folder']))
                        <span class="libreta-nota-carpeta">{{ $nota['folder'] }}</span>
                    @endif
                </li>
            @empty
                <li class="libreta-vacia" id="libretaVacia">Todavía no hay notas. Pulsa «+ Nota».</li>
            @endforelse
        </ul>
    </section>

    <section class="libreta-editor" aria-label="Nota abierta">
        <div class="libreta-cabecera">
            <h1 id="libretaTituloCabecera">Sin nota abierta</h1>
            <select class="libreta-selector" id="libretaCarpetaNota" title="Materia de la nota" disabled>
                <option value="">Sin materia</option>
                @foreach (($carpetas ?? []) as $carpeta)
                    <option value="{{ $carpeta }}">{{ $carpeta }}</option>
                @endforeach
            </select>

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
    const carpetas = document.getElementById('libretaCarpetas');
    const carpetaNota = document.getElementById('libretaCarpetaNota');
    const nuevaCarpeta = document.getElementById('libretaNuevaCarpeta');
    const listaMaterias = document.getElementById('libretaListaMaterias');
    const carpetaForm = document.getElementById('libretaCarpetaForm');
    const carpetaNombre = document.getElementById('libretaCarpetaNombre');
    const carpetaGuardar = document.getElementById('libretaCarpetaGuardar');
    const cuenta = document.getElementById('libretaCuenta');
    const registrado = raiz.dataset.registrado === '1';

    let notaActual = null;
    let guardadoPendiente = null;
    // '*' = todas; '' = sin materia; cualquier otro = nombre de la materia
    let carpetaActiva = '*';
    let carpetaRenombrando = '';

    // Materias conocidas. Se pintan desde aqui, no desde el servidor, para poder
    // crear, renombrar y borrar sin recargar la pagina.
    let materias = [];

    try {
        const datos = JSON.parse(document.getElementById('libretaDatos').textContent || '{}');
        materias = Array.isArray(datos.carpetas) ? datos.carpetas : [];
    } catch (e) {
        materias = [];
    }

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
        // Solo enlaces http(s) y con el atributo bien cerrado: antes,
        // [x](a" onmouseover="...) inyectaba atributos en la nota.
        salida = salida.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (todo, texto, destino) => {
            const limpio = String(destino).replace(/[\"']/g, '');

            if (!/^https?:\/\//i.test(limpio)) {
                return texto;
            }

            return '<a href="' + limpio + '" target="_blank" rel="noopener noreferrer">' + texto + '</a>';
        });

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

    /*
     * Muestra u oculta las notas segun la materia elegida y lo que se busque.
     * Las dos cosas se aplican a la vez.
     */
    const aplicarFiltro = () => {
        const consulta = buscador.value.trim().toLowerCase();

        Array.from(lista.querySelectorAll('.libreta-nota')).forEach((elemento) => {
            const deCarpeta = carpetaActiva === '*'
                || (elemento.dataset.carpeta || '') === carpetaActiva;
            const buscado = consulta === ''
                || (elemento.dataset.titulo + ' ' + elemento.dataset.cuerpo).toLowerCase().includes(consulta);

            elemento.hidden = !(deCarpeta && buscado);
        });
    };

    const marcarCarpeta = () => {
        Array.from(carpetas.querySelectorAll('.libreta-carpeta[data-carpeta]')).forEach((boton) => {
            boton.classList.toggle('activa', (boton.dataset.carpeta || '') === carpetaActiva);
        });
    };

    /*
     * Cuenta cuantas notas hay por materia leyendo la lista del DOM: asi los
     * numeros cambian al crear o borrar una nota, sin volver a pedir la pagina.
     */
    const cuentas = () => {
        const resultado = { '*': 0, '': 0 };

        Array.from(lista.querySelectorAll('.libreta-nota')).forEach((elemento) => {
            const clave = elemento.dataset.carpeta || '';

            resultado['*'] = (resultado['*'] || 0) + 1;
            resultado[clave] = (resultado[clave] || 0) + 1;
        });

        return resultado;
    };

    const botonMateria = (clave, etiqueta, cuantos, conBorrar) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'libreta-carpeta' + (clave === carpetaActiva ? ' activa' : '');
        boton.dataset.carpeta = clave;

        if (clave === '*') {
            boton.title = 'Todas las notas';
        } else if (clave === '') {
            boton.title = 'Notas sin materia';
        } else {
            boton.title = 'Doble clic para renombrar';
        }

        const nombre = document.createElement('span');
        nombre.className = 'libreta-carpeta-nombre';
        nombre.textContent = etiqueta;
        boton.appendChild(nombre);

        const cuenta = document.createElement('span');
        cuenta.className = 'libreta-cuenta';
        cuenta.textContent = String(cuantos);
        boton.appendChild(cuenta);

        if (conBorrar) {
            const borrar = document.createElement('span');
            borrar.className = 'libreta-carpeta-borrar';
            borrar.dataset.borrarCarpeta = clave;
            borrar.title = 'Borrar materia';
            borrar.textContent = '×';
            boton.appendChild(borrar);
        }

        return boton;
    };

    const renderMaterias = () => {
        if (!listaMaterias) return;

        const cuenta = cuentas();
        listaMaterias.textContent = '';

        listaMaterias.appendChild(botonMateria('*', 'Todas', cuenta['*'] || 0, false));

        if ((cuenta[''] || 0) > 0) {
            listaMaterias.appendChild(botonMateria('', 'Sin materia', cuenta[''] || 0, false));
        }

        materias.forEach((materia) => {
            listaMaterias.appendChild(botonMateria(materia, materia, cuenta[materia] || 0, true));
        });
    };

    // El selector de la nota abierta se rehace con las materias actuales.
    const sincronizarSelector = () => {
        if (!carpetaNota) return;

        const actual = carpetaNota.value;

        carpetaNota.textContent = '';

        const sinMateria = document.createElement('option');
        sinMateria.value = '';
        sinMateria.textContent = 'Sin materia';
        carpetaNota.appendChild(sinMateria);

        materias.forEach((materia) => {
            const opcion = document.createElement('option');
            opcion.value = materia;
            opcion.textContent = materia;
            carpetaNota.appendChild(opcion);
        });

        if (materias.includes(actual)) {
            carpetaNota.value = actual;
        }
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
            carpetaNota.disabled = true;
            carpetaNota.value = '';
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
        carpetaNota.disabled = false;
        carpetaNota.value = elementoNota.dataset.carpeta || '';
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
        elemento.dataset.carpeta = nota.folder || '';

        const fuerte = document.createElement('strong');
        fuerte.textContent = nota.title;
        elemento.appendChild(fuerte);

        const pequena = document.createElement('small');
        pequena.textContent = 'ahora';
        elemento.appendChild(pequena);

        if (nota.folder) {
            const etiquetaCarpeta = document.createElement('span');
            etiquetaCarpeta.className = 'libreta-nota-carpeta';
            etiquetaCarpeta.textContent = nota.folder;
            elemento.appendChild(etiquetaCarpeta);
        }

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
                body: texto.value,
                folder: carpetaNota.value
            });

            elemento.dataset.titulo = datos.note.title;
            elemento.dataset.cuerpo = datos.note.body;
            elemento.dataset.actualizada = datos.note.updated_at;
            elemento.dataset.carpeta = datos.note.folder || '';

            // La etiqueta de materia de la lista se ajusta al momento.
            let etiqueta = elemento.querySelector('.libreta-nota-carpeta');

            if (datos.note.folder) {
                if (!etiqueta) {
                    etiqueta = document.createElement('span');
                    etiqueta.className = 'libreta-nota-carpeta';
                    elemento.appendChild(etiqueta);
                }
                etiqueta.textContent = datos.note.folder;
            } else if (etiqueta) {
                etiqueta.remove();
            }

            renderMaterias();
            aplicarFiltro();
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

    // Cambiar de materia en el selector guarda la nota al momento
    if (carpetaNota) {
        carpetaNota.addEventListener('change', () => guardar(true));
    }

    titulo.addEventListener('blur', () => guardar(true));
    texto.addEventListener('blur', () => guardar(true));

    document.addEventListener('keydown', (evento) => {
        if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 's') {
            // Solo si la libreta se puede ver: antes secuestraba Ctrl+S en toda la
            // pagina del contenedor aunque el panel estuviera cerrado.
            const panel = document.getElementById('libretaPanel');
            const visible = panel === null || ! panel.hidden;

            if (!visible || !notaActual) {
                return;
            }

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
            renderMaterias();
            aplicarFiltro();
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
            const datos = await enviar(raiz.dataset.urlNueva, {
                title: 'Nota sin título',
                body: '',
                folder: carpetaActiva === '*' ? '' : carpetaActiva
            });

            const vacia = lista.querySelector('.libreta-vacia');
            if (vacia) vacia.remove();

            const elemento = crearElemento(datos.note);
            lista.prepend(elemento);

            // Si se esta viendo una materia concreta y la nota no es de ella,
            // conviene verla: se pasa a "todas" para no dejarla escondida.
            if (carpetaActiva !== '*' && (datos.note.folder || '') !== carpetaActiva) {
                carpetaActiva = '*';
                marcarCarpeta();
            }

            aplicarFiltro();
            renderMaterias();
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

    buscador.addEventListener('input', () => aplicarFiltro());

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

    // -----------------------------------------------------------------
    // Materias
    // -----------------------------------------------------------------

    carpetas.addEventListener('click', (evento) => {
        const borrar = evento.target.closest('[data-borrar-carpeta]');

        if (borrar) {
            evento.stopPropagation();
            borrarCarpeta(borrar.dataset.borrarCarpeta);
            return;
        }

        const boton = evento.target.closest('.libreta-carpeta[data-carpeta]');

        if (!boton) return;

        carpetaActiva = boton.dataset.carpeta || '';
        marcarCarpeta();
        aplicarFiltro();
    });

    // Doble clic en una materia: renombrar
    carpetas.addEventListener('dblclick', (evento) => {
        const boton = evento.target.closest('.libreta-carpeta[data-carpeta]');

        if (!boton || !boton.dataset.carpeta) return;

        carpetaRenombrando = boton.dataset.carpeta;
        carpetaForm.hidden = false;
        carpetaNombre.value = carpetaRenombrando;
        carpetaNombre.focus();
        carpetaNombre.select();
    });

    nuevaCarpeta.addEventListener('click', () => {
        carpetaRenombrando = '';
        carpetaForm.hidden = false;
        carpetaNombre.value = '';
        carpetaNombre.focus();
    });

    carpetaGuardar.addEventListener('click', () => crearCarpeta());

    carpetaNombre.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            crearCarpeta();
        }

        if (evento.key === 'Escape') {
            carpetaForm.hidden = true;
            carpetaNombre.value = '';
        }
    });

    const crearCarpeta = async () => {
        const nombre = carpetaNombre.value.trim();

        if (nombre === '') return;

        try {
            if (carpetaRenombrando !== '') {
                const datos = await enviar('/libreta/carpeta/renombrar', { from: carpetaRenombrando, to: nombre });

                // Las notas que estaban en la materia vieja pasan a la nueva.
                Array.from(lista.querySelectorAll('.libreta-nota')).forEach((elemento) => {
                    if ((elemento.dataset.carpeta || '') === carpetaRenombrando) {
                        elemento.dataset.carpeta = nombre;
                        const etiqueta = elemento.querySelector('.libreta-nota-carpeta');
                        if (etiqueta) etiqueta.textContent = nombre;
                    }
                });

                if (carpetaActiva === carpetaRenombrando) {
                    carpetaActiva = nombre;
                }

                materias = datos.folders || materias;
            } else {
                const datos = await enviar('/libreta/carpeta', { name: nombre });
                materias = datos.folders || materias;
            }

            carpetaRenombrando = '';
            carpetaForm.hidden = true;
            carpetaNombre.value = '';

            renderMaterias();
            sincronizarSelector();
            aplicarFiltro();
            avisar('Materia guardada');
        } catch (error) {
            avisar(error.message);
        }
    };

    async function borrarCarpeta(nombre) {
        const cuantas = Array.from(lista.querySelectorAll('.libreta-nota'))
            .filter((elemento) => (elemento.dataset.carpeta || '') === nombre).length;

        const pregunta = window.vhConfirmar
            ? await window.vhConfirmar({
                titulo: 'Borrar materia',
                mensaje: 'Se va a borrar la materia «' + nombre + '». Sus ' + cuantas
                    + (cuantas === 1 ? ' nota se queda' : ' notas se quedan') + ' sin materia.',
                boton: 'Borrar materia'
            })
            : window.confirm('¿Borrar la materia «' + nombre + '»?');

        if (!pregunta) return;

        try {
            const datos = await enviar('/libreta/carpeta/borrar', { name: nombre });

            // Sus notas se quedan sin materia.
            Array.from(lista.querySelectorAll('.libreta-nota')).forEach((elemento) => {
                if ((elemento.dataset.carpeta || '') === nombre) {
                    elemento.dataset.carpeta = '';
                    const etiqueta = elemento.querySelector('.libreta-nota-carpeta');
                    if (etiqueta) etiqueta.remove();
                }
            });

            materias = datos.folders || materias.filter((materia) => materia !== nombre);

            if (carpetaActiva === nombre) {
                carpetaActiva = '*';
            }

            renderMaterias();
            sincronizarSelector();
            aplicarFiltro();
            avisar('Materia borrada');
        } catch (error) {
            avisar(error.message);
        }
    }

    renderMaterias();
    sincronizarSelector();

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
