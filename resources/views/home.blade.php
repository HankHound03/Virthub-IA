<!DOCTYPE html>
<html lang="es">
<style>
    .calendar-gadget {
        min-height: 250px;
    }

    .calendar-headline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .calendar-month-title {
        margin: 0;
        color: var(--vh-text);
        font-size: 15px;
        text-transform: capitalize;
    }

    .calendar-nav {
        display: inline-flex;
        gap: 6px;
    }

    .calendar-nav button {
        min-width: 32px;
        padding: 4px 8px;
        border: 1px solid var(--vh-border);
        background-color: var(--vh-button-bg);
        color: var(--vh-text);
        cursor: pointer;
        font-family: Monocraft Nerd Font, monospace;
        font-size: 12px;
    }

    .calendar-nav button:hover {
        background-color: var(--vh-button-hover);
    }

    .calendar-now {
        margin: 0 0 8px 0;
        color: var(--vh-text-soft);
        font-size: 12px;
        line-height: 1.4;
    }

    .calendar-weekday-row,
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 4px;
    }

    .calendar-weekday-row {
        margin-bottom: 4px;
    }

    .calendar-weekday {
        text-align: center;
        color: var(--vh-text-soft);
        font-size: 11px;
        padding: 4px 0;
    }

    .calendar-day {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        border: 1px solid var(--vh-border);
        background-color: rgba(0, 0, 0, 0.10);
        color: var(--vh-panel-text);
        font-size: 12px;
        cursor: pointer;
        font-family: Monocraft Nerd Font, monospace;
        padding: 0;
    }

    .calendar-day.is-out {
        opacity: 0.45;
    }

    .calendar-day.is-today {
        border-color: rgba(117, 225, 160, 0.62);
        background-color: rgba(117, 225, 160, 0.18);
        color: var(--vh-text);
        font-weight: 700;
    }

    .calendar-day.is-selected {
        border-color: rgba(122, 170, 255, 0.7);
        background-color: rgba(122, 170, 255, 0.24);
        color: var(--vh-text);
    }

    .calendar-day.has-events {
        border-color: rgba(255, 199, 104, 0.72);
        background-color: rgba(255, 199, 104, 0.22);
        color: var(--vh-text);
    }

    .calendar-day.has-events.is-selected {
        border-color: rgba(122, 170, 255, 0.84);
        background: linear-gradient(0deg, rgba(122, 170, 255, 0.24), rgba(255, 199, 104, 0.2));
    }

    .calendar-day.has-events.is-today {
        border-color: rgba(117, 225, 160, 0.78);
        box-shadow: inset 0 0 0 1px rgba(255, 199, 104, 0.6);
    }

    .calendar-events {
        margin-top: 10px;
        border-top: 1px solid var(--vh-border);
        padding-top: 8px;
    }

    .calendar-events-title {
        margin: 0 0 6px 0;
        color: var(--vh-text-soft);
        font-size: 12px;
    }

    .calendar-event-form {
        display: flex;
        gap: 6px;
        margin-bottom: 8px;
    }

    .calendar-event-form input {
        flex: 1;
        box-sizing: border-box;
        border: 1px solid var(--vh-border);
        background-color: var(--vh-button-bg);
        color: var(--vh-text);
        padding: 6px 8px;
        font-size: 12px;
        font-family: Monocraft Nerd Font, monospace;
    }

    .calendar-event-form button {
        border: 1px solid var(--vh-border);
        background-color: var(--vh-button-bg);
        color: var(--vh-text);
        padding: 6px 10px;
        font-size: 12px;
        cursor: pointer;
        font-family: Monocraft Nerd Font, monospace;
    }

    .calendar-event-form button:hover {
        background-color: var(--vh-button-hover);
    }

    .calendar-events-list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .calendar-events-list li {
        border: 1px solid var(--vh-border);
        background-color: rgba(0, 0, 0, 0.12);
        padding: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .calendar-events-list button {
        border: 1px solid rgba(255, 120, 120, 0.4);
        background-color: rgba(255, 120, 120, 0.12);
        color: #ffdede;
        padding: 4px 7px;
        font-size: 11px;
        cursor: pointer;
        font-family: Monocraft Nerd Font, monospace;
    }

    .calendar-events-list button:hover {
        background-color: rgba(255, 120, 120, 0.2);
    }

    .calendar-events-empty {
        color: var(--vh-text-soft);
        font-size: 12px;
    }
</style>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VirtHub</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
</head>
<body>
    <header>
        <div class="header-controls">
            <div class= "toggleable-sidebar" onclick="toggleMenu(event)" aria-label="Abrir menu" title="Menu">
                <span class="menu-icon" aria-hidden="true"></span>
                <div class="sidebar" onclick="event.stopPropagation()">
                    @include('partials.navigation-menu', ['currentUser' => $currentUser ?? null, 'currentPage' => 'home'])
                </div>
            </div>
            <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <span class="theme-icon" aria-hidden="true"></span>
            </div>
        </div>

        @if (!empty($currentUser))
            @php
                $headerProfileImage = (string) ($currentUser['profile_image_path'] ?? '');
                $headerFrameColor = (string) ($currentUser['profile_frame_color'] ?? '#6ea8ff');
                $headerInitial = strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1));
            @endphp
            <div class="header-profile-dock toggleable-profile-menu" onclick="toggleProfileMenu(event)" title="Menu de perfil" aria-label="Menu de perfil">
                <div class="profile-aero-frame profile-aero-frame-sm" style="--profile-frame-color: {{ $headerFrameColor }};">
                    @if ($headerProfileImage !== '')
                        <img src="{{ asset($headerProfileImage) }}" alt="Foto de perfil de {{ $currentUser['username'] }}" loading="lazy">
                    @else
                        <span>{{ $headerInitial }}</span>
                    @endif
                </div>
                <div class="profile-menu" onclick="event.stopPropagation()">
                    @if (($currentUser['role'] ?? 'guest') !== 'guest')
                        <button type="button" onclick="location.href='{{ url('/configuracion') }}'">Configuracion</button>
                    @endif
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit">Cerrar Sesion</button>
                    </form>
                </div>
            </div>
        @endif
        <h1>VirtHub</h1>
    </header>

    @include('partials.chat-widget')

    <div class="home-panels" id="gadgetBoard">
        <aside class="linux-news-panel gadget gadget-size-normal" data-gadget-id="news" data-gadget-size="normal" data-default-size="normal">
            <div class="gadget-head">
                <h3>Noticias</h3>
                @if (!empty($currentUser))
                    <div class="gadget-actions gadget-actions-compact">
                        <span class="gadget-drag-chip" title="Arrastra para mover" aria-label="Arrastra para mover">⠿</span>
                        <button type="button" class="gadget-mini-btn gadget-size-text-btn" onclick="cycleHomeGadgetSize(event, this)" title="Cambiar tamano" aria-label="Cambiar tamano">Normal</button>
                    </div>
                @endif
            </div>

            <div class="news-filter-bar">
                <button type="button" class="news-filter-btn" data-news-filter="all" onclick="applyNewsFilter('all')">Todos</button>
                <button type="button" class="news-filter-btn" data-news-filter="linux" onclick="applyNewsFilter('linux')">Linux</button>
                <button type="button" class="news-filter-btn" data-news-filter="cyber" onclick="applyNewsFilter('cyber')">Ciberseguridad</button>
            </div>

            <div class="news-group" data-news-type="linux">
                <h3>Noticias Linux</h3>
                <ul id="linux-news-list">
                    <li>Cargando noticias...</li>
                </ul>
            </div>

            <div class="news-group" data-news-type="cyber">
                <h3 class="news-section-title">Noticias Ciberseguridad</h3>
                <ul id="cyber-news-list">
                    <li>Cargando noticias...</li>
                </ul>
            </div>
        </aside>

        <div class="login-screen gadget access-gadget gadget-size-normal" data-gadget-id="access" data-gadget-size="normal" data-default-size="normal">
            <div class="gadget-head">
                <h3>Acceso</h3>
                @if (!empty($currentUser))
                    <div class="gadget-actions gadget-actions-compact">
                        <span class="gadget-drag-chip" title="Arrastra para mover" aria-label="Arrastra para mover">⠿</span>
                        <button type="button" class="gadget-mini-btn gadget-size-text-btn" onclick="cycleHomeGadgetSize(event, this)" title="Cambiar tamano" aria-label="Cambiar tamano">Normal</button>
                    </div>
                @endif
            </div>

            @if (!empty($currentUser))
                <p class="access-intro">
                    Sesion iniciada. Usa estos accesos rapidos para entrar a las secciones principales de VirtHub.
                </p>
            @else
                <p class="access-intro">
                    No has iniciado sesion. Entra con tu cuenta o usa Invitado para una sesion temporal.
                </p>
            @endif

            @if (session('error'))
                <p class="auth-message auth-error">{{ session('error') }}</p>
            @endif

            @if (session('success'))
                <p class="auth-message auth-success">{{ session('success') }}</p>
            @endif

            @if (!empty($currentUser))
                @if (($currentUser['role'] ?? 'user') === 'guest')
                    <p class="auth-message auth-success" id="guestRemainingLabel" data-guest-remaining="{{ (int) ($guestRemainingSeconds ?? 0) }}">
                        Tiempo restante invitado: calculando...
                    </p>
                @endif

                <div class="quick-links-grid access-links-grid">
                    <button type="button" onclick="location.href='{{ url('/foro') }}'">Foro</button>
                    <button type="button" onclick="location.href='{{ url('/sugerencias') }}'">Sugerencias</button>
                    <button type="button" onclick="location.href='{{ url('/contenedor') }}'">Contenedor</button>
                    @if (($currentUser['role'] ?? 'user') === 'admin')
                        <button type="button" onclick="location.href='{{ url('/admin/users') }}'">Panel Admin</button>
                    @endif
                </div>
            @else
                <form method="POST" action="/login" id="loginForm">
                    @csrf
                    <label for="username">Usuario<p><input type="text" name="username" id="username" value="{{ old('username') }}" required><p></label>
                    <label for="password">Contrasena<p><input type="password" name="password" id="password" required onkeypress="if(event.key==='Enter') document.getElementById('loginForm').requestSubmit();"><p></label>
                    <button type="submit" style="display:none;">Iniciar Sesion</button>
                </form>

                <form method="POST" action="/guest-login" id="guestLoginForm">
                    @csrf
                </form>

                <div class="quick-links-grid access-links-grid">
                    <button type="button" onclick="document.getElementById('loginForm')?.requestSubmit()">Iniciar Sesión</button>
                    <button type="button" onclick="document.getElementById('guestLoginForm')?.submit()">Invitado</button>
                    <button type="button" onclick="location.href='{{ url('/foro') }}'">Foro</button>
                    <button type="button" onclick="location.href='{{ url('/sugerencias') }}'">Sugerencias</button>
                </div>
            @endif
        </div>

        @if (!empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest'))
        <aside class="linux-news-panel gadget calendar-gadget gadget-size-wide" data-gadget-id="calendar" data-gadget-size="wide" data-default-size="wide">
            <div class="gadget-head">
                <h3>Mini calendario</h3>
                @if (!empty($currentUser))
                    <div class="gadget-actions gadget-actions-compact">
                        <span class="gadget-drag-chip" title="Arrastra para mover" aria-label="Arrastra para mover">⠿</span>
                        <button type="button" class="gadget-mini-btn gadget-size-text-btn" onclick="cycleHomeGadgetSize(event, this)" title="Cambiar tamano" aria-label="Cambiar tamano">Ancho</button>
                    </div>
                @endif
            </div>

            <div class="calendar-headline">
                <h4 class="calendar-month-title" id="miniCalendarMonth">Cargando...</h4>
                <div class="calendar-nav">
                    <button type="button" id="miniCalendarPrev" aria-label="Mes anterior">◀</button>
                    <button type="button" id="miniCalendarNext" aria-label="Mes siguiente">▶</button>
                </div>
            </div>

            <p class="calendar-now" id="miniCalendarNow">Detectando hora local...</p>

            <div class="calendar-weekday-row" aria-hidden="true">
                <span class="calendar-weekday">L</span>
                <span class="calendar-weekday">M</span>
                <span class="calendar-weekday">X</span>
                <span class="calendar-weekday">J</span>
                <span class="calendar-weekday">V</span>
                <span class="calendar-weekday">S</span>
                <span class="calendar-weekday">D</span>
            </div>

            <div class="calendar-grid" id="miniCalendarGrid"></div>

            <section class="calendar-events" aria-label="Eventos de calendario">
                <p class="calendar-events-title" id="miniCalendarSelectedDate">Eventos del dia: -</p>
                <form class="calendar-event-form" id="miniCalendarEventForm">
                    <input type="text" id="miniCalendarEventInput" maxlength="120" placeholder="Ejemplo: Reunion 10:30 con equipo" autocomplete="off">
                    <button type="submit">Guardar</button>
                </form>
                <ul class="calendar-events-list" id="miniCalendarEventsList"></ul>
            </section>
        </aside>
        @endif

        @if (!empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest'))
        <aside class="linux-news-panel gadget todo-gadget gadget-size-normal" data-gadget-id="todo" data-gadget-size="normal" data-default-size="normal">
            <div class="gadget-head">
                <h3>To-do</h3>
                @if (!empty($currentUser))
                    <div class="gadget-actions gadget-actions-compact">
                        <span class="gadget-drag-chip" title="Arrastra para mover" aria-label="Arrastra para mover">⠿</span>
                        <button type="button" class="gadget-mini-btn gadget-size-text-btn" onclick="cycleHomeGadgetSize(event, this)" title="Cambiar tamano" aria-label="Cambiar tamano">Normal</button>
                    </div>
                @endif
            </div>

            <section class="todo-widget" aria-label="Tareas pendientes">
                <form id="todoForm" class="todo-form">
                    <input id="todoInput" type="text" maxlength="120" placeholder="Escribe una tarea y presiona Enter" autocomplete="off">
                    <button type="submit">Agregar</button>
                </form>
                <ul id="todoList" class="todo-list"></ul>
            </section>

        </aside>

        <aside class="linux-news-panel gadget notes-gadget gadget-size-normal" data-gadget-id="notes" data-gadget-size="normal" data-default-size="normal">
            <div class="gadget-head">
                <h3>Notas rapidas</h3>
                @if (!empty($currentUser))
                    <div class="gadget-actions gadget-actions-compact">
                        <span class="gadget-drag-chip" title="Arrastra para mover" aria-label="Arrastra para mover">⠿</span>
                        <button type="button" class="gadget-mini-btn gadget-size-text-btn" onclick="cycleHomeGadgetSize(event, this)" title="Cambiar tamano" aria-label="Cambiar tamano">Normal</button>
                    </div>
                @endif
            </div>

            <section class="notes-widget" aria-label="Notas rapidas">
                <textarea id="quickNotesInput" class="quick-notes-input" rows="7" maxlength="2400" placeholder="Anota ideas, recordatorios o pasos rapidos..."></textarea>
            </section>
        </aside>
        @endif

        @if (!empty($currentUser) && ($currentUser['role'] ?? 'user') === 'admin')
            <aside class="linux-news-panel gadget system-status-panel gadget-size-wide" data-gadget-id="system" data-gadget-size="wide" data-default-size="wide">
                <div class="gadget-head">
                    <h3>Estado del Sistema</h3>
                    @if (!empty($currentUser))
                        <div class="gadget-actions gadget-actions-compact">
                            <span class="gadget-drag-chip" title="Arrastra para mover" aria-label="Arrastra para mover">⠿</span>
                            <button type="button" class="gadget-mini-btn gadget-size-text-btn" onclick="cycleHomeGadgetSize(event, this)" title="Cambiar tamano" aria-label="Cambiar tamano">Ancho</button>
                        </div>
                    @endif
                </div>

                <div class="system-status-live" id="systemStatusList">
                    <div class="system-status-meta">
                        <span>Ultima muestra: <strong id="sys_timestamp">Cargando hora local...</strong></span>
                        <span>Zona local: <strong id="sys_timezone_label">Detectando...</strong></span>
                    </div>

                    <div class="system-metric-grid">
                        <article class="system-metric-card">
                            <header><h4>CPU</h4><strong id="sys_cpu">{{ isset($systemStatus['cpu_usage_percent']) ? $systemStatus['cpu_usage_percent'] . '%' : '-' }}</strong></header>
                            <div class="system-meter"><span id="sys_cpu_bar" style="width: {{ isset($systemStatus['cpu_usage_percent']) ? max(0, min(100, (float) $systemStatus['cpu_usage_percent'])) : 0 }}%"></span></div>
                            <canvas id="sys_cpu_chart" class="system-sparkline" width="320" height="84" aria-label="Grafico CPU"></canvas>
                        </article>

                        <article class="system-metric-card">
                            <header><h4>RAM</h4><strong id="sys_ram">{{ isset($systemStatus['ram_used_percent']) ? $systemStatus['ram_used_percent'] . '%' : '-' }}</strong></header>
                            <p class="system-metric-sub">Usada MB: <strong id="sys_ram_mb">{{ $systemStatus['ram_used_mb'] ?? '-' }}</strong></p>
                            <div class="system-meter"><span id="sys_ram_bar" style="width: {{ isset($systemStatus['ram_used_percent']) ? max(0, min(100, (float) $systemStatus['ram_used_percent'])) : 0 }}%"></span></div>
                            <canvas id="sys_ram_chart" class="system-sparkline" width="320" height="84" aria-label="Grafico RAM"></canvas>
                        </article>

                        <article class="system-metric-card">
                            <header><h4>Disco</h4><strong id="sys_disk">{{ isset($systemStatus['disk_used_percent']) ? $systemStatus['disk_used_percent'] . '%' : '-' }}</strong></header>
                            <div class="system-meter"><span id="sys_disk_bar" style="width: {{ isset($systemStatus['disk_used_percent']) ? max(0, min(100, (float) $systemStatus['disk_used_percent'])) : 0 }}%"></span></div>
                            <canvas id="sys_disk_chart" class="system-sparkline" width="320" height="84" aria-label="Grafico Disco"></canvas>
                        </article>

                        <article class="system-metric-card system-metric-card-compact">
                            <header><h4>WebTop</h4><strong id="sys_webtop">{{ !empty($systemStatus['webtop_online']) ? 'Online' : 'Offline' }}</strong></header>
                            <div class="system-state-pill" id="sys_webtop_pill">{{ !empty($systemStatus['webtop_online']) ? 'Servicio activo' : 'Servicio no disponible' }}</div>
                        </article>

                    </div>
                </div>
            </aside>
        @endif
    </div>

    <footer>Codename Virthub 0.9c PreRelease</footer>
    <script>
        function getUserKey() {
            return @json($currentUser['username'] ?? 'guest');
        }

        function getThemeStorageKey() {
            return 'virthub_dark_mode_' + getUserKey();
        }

        function getNewsFilterKey() {
            return 'virthub_news_filter_' + getUserKey();
        }

        function getGadgetOrderKey() {
            return 'virthub_gadget_order_' + getUserKey();
        }

        function getGadgetSizeKey() {
            return 'virthub_gadget_size_' + getUserKey();
        }

        function getProductivityKey() {
            return 'virthub_productivity_' + getUserKey();
        }

        function getCalendarEventsKey() {
            return 'virthub_calendar_events_' + getUserKey();
        }

        function isHomeResponsiveView() {
            return window.matchMedia('(max-width: 768px)').matches;
        }

        function canCustomizeHome() {
            return @json(!empty($currentUser)) && !isHomeResponsiveView();
        }

        function setHomeGadgetSize(gadget, size) {
            if (!gadget) return;

            const allowed = ['normal', 'wide'];
            const nextSize = allowed.includes(size) ? size : 'normal';
            gadget.classList.remove('gadget-size-normal', 'gadget-size-wide', 'gadget-size-tall');
            gadget.classList.add('gadget-size-' + nextSize);
            gadget.dataset.gadgetSize = nextSize;

            const btn = gadget.querySelector('.gadget-mini-btn');
            if (btn) {
                const labels = {
                    normal: 'Normal',
                    wide: 'Ancho',
                };
                btn.textContent = labels[nextSize] || 'Normal';
            }
        }

        function saveGadgetSizes() {
            if (!canCustomizeHome()) return;

            const board = document.getElementById('gadgetBoard');
            if (!board) return;

            const payload = {};
            board.querySelectorAll('.gadget').forEach(gadget => {
                const id = gadget.dataset.gadgetId;
                if (!id) return;
                payload[id] = gadget.dataset.gadgetSize || 'normal';
            });

            localStorage.setItem(getGadgetSizeKey(), JSON.stringify(payload));
        }

        function applySavedGadgetSizes() {
            const board = document.getElementById('gadgetBoard');
            if (!board) return;

            if (!canCustomizeHome()) {
                board.querySelectorAll('.gadget').forEach(gadget => {
                    const defaultSize = gadget.dataset.defaultSize || 'normal';
                    setHomeGadgetSize(gadget, defaultSize);
                });
                return;
            }

            const raw = localStorage.getItem(getGadgetSizeKey());
            let payload = {};
            try {
                payload = raw ? JSON.parse(raw) : {};
            } catch (error) {
                payload = {};
            }

            board.querySelectorAll('.gadget').forEach(gadget => {
                const id = gadget.dataset.gadgetId;
                const defaultSize = gadget.dataset.defaultSize || gadget.dataset.gadgetSize || 'normal';
                const size = (id && payload[id]) ? payload[id] : defaultSize;
                setHomeGadgetSize(gadget, size);
            });
        }

        function cycleHomeGadgetSize(event, triggerBtn) {
            if (!canCustomizeHome()) return;

            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            const gadget = triggerBtn ? triggerBtn.closest('.gadget') : null;
            if (!gadget) return;

            const sizes = ['normal', 'wide'];
            const current = gadget.dataset.gadgetSize || 'normal';
            const index = sizes.indexOf(current);
            const next = sizes[(index >= 0 ? index + 1 : 0) % sizes.length];
            setHomeGadgetSize(gadget, next);
            saveGadgetSizes();
        }

        function applySidebarState() {
            const launcher = document.querySelector('.toggleable-sidebar');
            if (!launcher) return;

            launcher.classList.remove('is-open');

            const profileLauncher = document.querySelector('.toggleable-profile-menu');
            if (profileLauncher) {
                profileLauncher.classList.remove('is-open');
            }
        }

        function applyThemeState() {
            const isDark = localStorage.getItem(getThemeStorageKey()) === '1';
            document.body.classList.toggle('dark-mode', isDark);

            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.classList.toggle('is-dark', isDark);
                themeToggle.title = isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
            }
        }

        function toggleTheme() {
            const isDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem(getThemeStorageKey(), isDark ? '1' : '0');

            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.classList.toggle('is-dark', isDark);
                themeToggle.title = isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
            }
        }

        function toggleMenu(event) {
            if (event) {
                event.stopPropagation();
            }

            const launcher = document.querySelector('.toggleable-sidebar');
            if (!launcher) return;

            launcher.classList.toggle('is-open');
        }

        function toggleProfileMenu(event) {
            if (event) {
                event.stopPropagation();
            }

            const launcher = document.querySelector('.toggleable-profile-menu');
            if (!launcher) return;

            launcher.classList.toggle('is-open');
        }

        function saveGadgetOrder() {
            if (!canCustomizeHome()) return;

            const board = document.getElementById('gadgetBoard');
            if (!board) return;

            const order = Array.from(board.querySelectorAll('.gadget')).map(g => g.dataset.gadgetId);
            localStorage.setItem(getGadgetOrderKey(), JSON.stringify(order));
        }

        function applySavedGadgetOrder() {
            const board = document.getElementById('gadgetBoard');
            if (!board) return;

            const knownOrder = ['news', 'access', 'calendar', 'todo', 'notes', 'system'];

            if (!canCustomizeHome()) {
                const defaultOrder = ['news', 'access', 'calendar', 'todo', 'notes', 'system'];
                defaultOrder.forEach(id => {
                    const gadget = board.querySelector(`.gadget[data-gadget-id="${id}"]`);
                    if (gadget) {
                        board.appendChild(gadget);
                    }
                });
                return;
            }

            const raw = localStorage.getItem(getGadgetOrderKey());
            if (!raw) return;

            try {
                const order = JSON.parse(raw);
                if (!Array.isArray(order)) return;

                const finalOrder = [];
                order.forEach(id => {
                    if (id === 'productivity') {
                        if (!finalOrder.includes('todo')) {
                            finalOrder.push('todo');
                        }
                        if (!finalOrder.includes('notes')) {
                            finalOrder.push('notes');
                        }
                        return;
                    }
                    if (knownOrder.includes(id) && !finalOrder.includes(id)) {
                        finalOrder.push(id);
                    }
                });
                knownOrder.forEach(id => {
                    if (!finalOrder.includes(id)) {
                        finalOrder.push(id);
                    }
                });

                finalOrder.forEach(id => {
                    const gadget = board.querySelector(`.gadget[data-gadget-id="${id}"]`);
                    if (gadget) {
                        board.appendChild(gadget);
                    }
                });
            } catch (error) {
                // Ignore invalid local storage payloads.
            }
        }

        function animateGadgetReflow(board, mutateLayout) {
            if (!board || typeof mutateLayout !== 'function') return;

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const first = new Map();
            board.querySelectorAll('.gadget').forEach(gadget => {
                first.set(gadget, gadget.getBoundingClientRect());
            });

            mutateLayout();

            if (prefersReducedMotion) return;

            board.querySelectorAll('.gadget').forEach(gadget => {
                if (gadget.classList.contains('dragging')) return;

                const start = first.get(gadget);
                if (!start) return;

                const end = gadget.getBoundingClientRect();
                const dx = start.left - end.left;
                const dy = start.top - end.top;

                if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) return;

                gadget.getAnimations().forEach(animation => animation.cancel());

                gadget.animate(
                    [
                        { transform: `translate(${dx}px, ${dy}px)` },
                        { transform: 'translate(0, 0)' },
                    ],
                    {
                        duration: 160,
                        easing: 'cubic-bezier(0.22, 0.9, 0.2, 1)',
                    }
                );
            });
        }

        function enableHomeGadgetDnD() {
            if (!canCustomizeHome()) return;

            const board = document.getElementById('gadgetBoard');
            if (!board) return;
            let lastReflowAnimationAt = 0;

            const gadgets = Array.from(board.querySelectorAll('.gadget'));
            gadgets.forEach(gadget => {
                gadget.setAttribute('draggable', 'true');
                gadget.dataset.dragFromHead = '0';
                gadget.dataset.dragBlocked = '0';

                gadget.addEventListener('mousedown', event => {
                    const isInteractive = !!event.target.closest('button, input, textarea, select, a, label, form');
                    const isHeaderControl = !!event.target.closest('.gadget-mini-btn');

                    gadget.dataset.dragBlocked = (isInteractive || isHeaderControl) ? '1' : '0';
                    gadget.dataset.dragFromHead = (isInteractive || isHeaderControl) ? '0' : '1';
                });

                gadget.addEventListener('dragstart', event => {
                    if (gadget.dataset.dragFromHead !== '1' || gadget.dataset.dragBlocked === '1') {
                        event.preventDefault();
                        return;
                    }

                    gadget.classList.add('dragging');
                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', gadget.dataset.gadgetId || '');
                    }
                });

                gadget.addEventListener('dragend', () => {
                    gadget.classList.remove('dragging');
                    gadget.dataset.dragFromHead = '0';
                    gadget.dataset.dragBlocked = '0';
                    saveGadgetOrder();
                });
            });

            board.addEventListener('dragover', event => {
                event.preventDefault();
                const dragging = board.querySelector('.gadget.dragging');
                const target = event.target.closest('.gadget');
                if (!dragging || !target || dragging === target) return;

                const rect = target.getBoundingClientRect();
                const before = event.clientY < (rect.top + rect.height / 2);
                const insertionPoint = before ? target : target.nextSibling;

                if (insertionPoint === dragging || dragging.nextSibling === insertionPoint) {
                    return;
                }

                const now = performance.now();
                if (now - lastReflowAnimationAt < 70) {
                    board.insertBefore(dragging, insertionPoint);
                    return;
                }

                lastReflowAnimationAt = now;
                animateGadgetReflow(board, () => {
                    board.insertBefore(dragging, insertionPoint);
                });
            });

            board.addEventListener('drop', event => {
                event.preventDefault();
                saveGadgetOrder();
            });

            window.addEventListener('mouseup', () => {
                board.querySelectorAll('.gadget').forEach(gadget => {
                    gadget.dataset.dragFromHead = '0';
                    gadget.dataset.dragBlocked = '0';
                });
            });
        }

        function applyNewsFilter(filter, persist = true) {
            const groups = document.querySelectorAll('.news-group');
            const buttons = document.querySelectorAll('.news-filter-btn');

            groups.forEach(group => {
                const groupType = group.dataset.newsType;
                const shouldShow = filter === 'all' || groupType === filter;
                group.style.display = shouldShow ? 'block' : 'none';
            });

            buttons.forEach(btn => {
                btn.classList.toggle('is-active', btn.dataset.newsFilter === filter);
            });

            if (persist) {
                localStorage.setItem(getNewsFilterKey(), filter);
            }
        }

        function applySavedNewsFilter() {
            const savedFilter = localStorage.getItem(getNewsFilterKey()) || 'all';
            applyNewsFilter(savedFilter, false);
        }

        function sanitizeTodoText(value) {
            const text = String(value || '').replace(/\s+/g, ' ').trim();
            if (!text) return '';
            return text.slice(0, 120);
        }

        function readProductivityState() {
            const fallback = { todos: [], notes: '' };
            const raw = localStorage.getItem(getProductivityKey());
            if (!raw) return fallback;

            try {
                const parsed = JSON.parse(raw);
                const todos = Array.isArray(parsed.todos)
                    ? parsed.todos
                        .map(item => ({
                            id: String(item.id || ''),
                            text: sanitizeTodoText(item.text || ''),
                            done: !!item.done,
                        }))
                        .filter(item => item.id && item.text)
                        .slice(0, 120)
                    : [];

                const notes = String(parsed.notes || '').slice(0, 2400);
                return { todos, notes };
            } catch (error) {
                return fallback;
            }
        }

        function saveProductivityState(state) {
            localStorage.setItem(getProductivityKey(), JSON.stringify({
                todos: Array.isArray(state.todos) ? state.todos : [],
                notes: String(state.notes || ''),
            }));
        }

        function renderTodoList(state) {
            const list = document.getElementById('todoList');
            if (!list) return;

            list.innerHTML = '';

            if (!state.todos.length) {
                const empty = document.createElement('li');
                empty.className = 'todo-empty';
                empty.textContent = 'Sin tareas por ahora.';
                list.appendChild(empty);
                return;
            }

            state.todos.forEach(item => {
                const li = document.createElement('li');
                li.className = 'todo-item';
                li.dataset.todoId = item.id;

                const check = document.createElement('input');
                check.type = 'checkbox';
                check.checked = item.done;
                check.setAttribute('aria-label', 'Marcar tarea completada');
                check.addEventListener('change', () => {
                    const target = state.todos.find(todo => todo.id === item.id);
                    if (!target) return;
                    target.done = check.checked;
                    saveProductivityState(state);
                    renderTodoList(state);
                });

                const text = document.createElement('span');
                text.className = 'todo-item-text';
                if (item.done) {
                    text.classList.add('is-done');
                }
                text.textContent = item.text;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'todo-remove-btn';
                removeBtn.textContent = 'Quitar';
                removeBtn.setAttribute('aria-label', 'Eliminar tarea');
                removeBtn.addEventListener('click', () => {
                    state.todos = state.todos.filter(todo => todo.id !== item.id);
                    saveProductivityState(state);
                    renderTodoList(state);
                });

                li.appendChild(check);
                li.appendChild(text);
                li.appendChild(removeBtn);
                list.appendChild(li);
            });
        }

        function initProductivityWidget() {
            const todoForm = document.getElementById('todoForm');
            const todoInput = document.getElementById('todoInput');
            const notesInput = document.getElementById('quickNotesInput');
            if (!todoForm || !todoInput || !notesInput) return;

            const state = readProductivityState();
            notesInput.value = state.notes;
            renderTodoList(state);

            todoForm.addEventListener('submit', event => {
                event.preventDefault();
                const text = sanitizeTodoText(todoInput.value);
                if (!text) return;

                state.todos.unshift({
                    id: 'todo_' + Date.now() + '_' + Math.random().toString(16).slice(2, 8),
                    text,
                    done: false,
                });
                if (state.todos.length > 120) {
                    state.todos = state.todos.slice(0, 120);
                }

                saveProductivityState(state);
                renderTodoList(state);
                todoInput.value = '';
                todoInput.focus();
            });

            notesInput.addEventListener('input', () => {
                state.notes = String(notesInput.value || '').slice(0, 2400);
                saveProductivityState(state);
            });
        }

        const systemMetricHistory = {
            cpu: [],
            ram: [],
            disk: [],
        };
        const SYSTEM_HISTORY_LIMIT = 24;
        let miniCalendarCursor = null;
        let miniCalendarSelectedIso = '';
        let miniCalendarEventsState = {};

        function getUserTimeZone() {
            try {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                return tz || 'UTC';
            } catch (error) {
                return 'UTC';
            }
        }

        function getUserOffsetLabel(referenceDate = new Date()) {
            const offsetMinutes = -referenceDate.getTimezoneOffset();
            const sign = offsetMinutes >= 0 ? '+' : '-';
            const absolute = Math.abs(offsetMinutes);
            const hh = String(Math.floor(absolute / 60)).padStart(2, '0');
            const mm = String(absolute % 60).padStart(2, '0');
            return `GMT${sign}${hh}:${mm}`;
        }

        function formatLocalDateTime(date) {
            return date.toLocaleString('es-MX', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        }

        function getSystemTimestampLocal(status) {
            const rawUtc = String(status.timestamp_utc || '').trim();
            if (rawUtc) {
                const date = new Date(rawUtc);
                if (!Number.isNaN(date.getTime())) {
                    return formatLocalDateTime(date);
                }
            }

            const rawLocal = String(status.timestamp || '').trim();
            if (rawLocal) {
                return rawLocal;
            }

            return '-';
        }

        function updateLocalTimezoneLabels() {
            const tzNode = document.getElementById('sys_timezone_label');
            if (tzNode) {
                tzNode.textContent = `${getUserTimeZone()} (${getUserOffsetLabel()})`;
            }
        }

        function toDateKey(date) {
            const yyyy = String(date.getFullYear());
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        function sanitizeCalendarEventText(value) {
            return String(value || '').replace(/\s+/g, ' ').trim().slice(0, 120);
        }

        function readCalendarEventsState() {
            const raw = localStorage.getItem(getCalendarEventsKey());
            if (!raw) return {};

            try {
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                    return {};
                }

                const next = {};
                Object.keys(parsed).forEach(dateKey => {
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateKey)) return;
                    const items = Array.isArray(parsed[dateKey]) ? parsed[dateKey] : [];
                    const cleaned = items
                        .map(item => sanitizeCalendarEventText(item))
                        .filter(item => item !== '')
                        .slice(0, 20);

                    if (cleaned.length) {
                        next[dateKey] = cleaned;
                    }
                });

                return next;
            } catch (error) {
                return {};
            }
        }

        function saveCalendarEventsState() {
            localStorage.setItem(getCalendarEventsKey(), JSON.stringify(miniCalendarEventsState));
        }

        function renderMiniCalendarEvents() {
            const title = document.getElementById('miniCalendarSelectedDate');
            const list = document.getElementById('miniCalendarEventsList');
            if (!title || !list || !miniCalendarSelectedIso) return;

            const selectedDate = new Date(`${miniCalendarSelectedIso}T00:00:00`);
            const selectedLabel = selectedDate.toLocaleDateString('es-MX', {
                weekday: 'long',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            });
            title.textContent = `Eventos del dia: ${selectedLabel}`;

            const events = Array.isArray(miniCalendarEventsState[miniCalendarSelectedIso])
                ? miniCalendarEventsState[miniCalendarSelectedIso]
                : [];

            list.innerHTML = '';

            if (!events.length) {
                const empty = document.createElement('li');
                empty.className = 'calendar-events-empty';
                empty.textContent = 'Sin eventos para esta fecha.';
                list.appendChild(empty);
                return;
            }

            events.forEach((eventText, index) => {
                const item = document.createElement('li');
                const text = document.createElement('span');
                text.textContent = eventText;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = 'Quitar';
                remove.addEventListener('click', () => {
                    const bucket = Array.isArray(miniCalendarEventsState[miniCalendarSelectedIso])
                        ? miniCalendarEventsState[miniCalendarSelectedIso]
                        : [];
                    bucket.splice(index, 1);

                    if (bucket.length) {
                        miniCalendarEventsState[miniCalendarSelectedIso] = bucket;
                    } else {
                        delete miniCalendarEventsState[miniCalendarSelectedIso];
                    }

                    saveCalendarEventsState();
                    renderMiniCalendarEvents();
                });

                item.appendChild(text);
                item.appendChild(remove);
                list.appendChild(item);
            });
        }

        function setMiniCalendarSelectedDate(isoDate) {
            miniCalendarSelectedIso = isoDate;
            renderMiniCalendar();
            renderMiniCalendarEvents();
        }

        function buildCalendarGrid(baseDate) {
            const year = baseDate.getFullYear();
            const month = baseDate.getMonth();

            const firstDay = new Date(year, month, 1);
            const firstWeekday = (firstDay.getDay() + 6) % 7;
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrevMonth = new Date(year, month, 0).getDate();
            const today = new Date();

            const cells = [];

            for (let i = 0; i < firstWeekday; i += 1) {
                const day = daysInPrevMonth - firstWeekday + i + 1;
                const date = new Date(year, month - 1, day);
                cells.push({ day, inMonth: false, isToday: false, iso: toDateKey(date) });
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const isToday = (
                    today.getFullYear() === year
                    && today.getMonth() === month
                    && today.getDate() === day
                );
                const date = new Date(year, month, day);
                cells.push({ day, inMonth: true, isToday, iso: toDateKey(date) });
            }

            while (cells.length < 42) {
                const day = cells.length - (firstWeekday + daysInMonth) + 1;
                const date = new Date(year, month + 1, day);
                cells.push({ day, inMonth: false, isToday: false, iso: toDateKey(date) });
            }

            return cells;
        }

        function renderMiniCalendar() {
            const grid = document.getElementById('miniCalendarGrid');
            const monthNode = document.getElementById('miniCalendarMonth');
            const nowNode = document.getElementById('miniCalendarNow');
            if (!grid || !monthNode || !nowNode) return;

            const viewDate = miniCalendarCursor || new Date();
            const monthTitle = viewDate.toLocaleDateString('es-MX', {
                month: 'long',
                year: 'numeric',
            });

            monthNode.textContent = monthTitle;
            nowNode.textContent = `Ahora: ${formatLocalDateTime(new Date())} | ${getUserTimeZone()} (${getUserOffsetLabel()})`;

            const cells = buildCalendarGrid(viewDate);
            grid.innerHTML = '';

            cells.forEach(cell => {
                const node = document.createElement('button');
                node.type = 'button';
                node.className = 'calendar-day';
                node.dataset.isoDate = cell.iso || '';
                if (!cell.inMonth) {
                    node.classList.add('is-out');
                }
                if (cell.isToday) {
                    node.classList.add('is-today');
                }
                const dayEvents = Array.isArray(miniCalendarEventsState[cell.iso]) ? miniCalendarEventsState[cell.iso] : [];
                if (dayEvents.length > 0) {
                    node.classList.add('has-events');
                }
                if (miniCalendarSelectedIso && cell.iso === miniCalendarSelectedIso) {
                    node.classList.add('is-selected');
                }
                node.textContent = String(cell.day);
                node.addEventListener('click', () => {
                    if (!cell.iso) return;
                    setMiniCalendarSelectedDate(cell.iso);
                });
                grid.appendChild(node);
            });
        }

        function initMiniCalendar() {
            const grid = document.getElementById('miniCalendarGrid');
            const prev = document.getElementById('miniCalendarPrev');
            const next = document.getElementById('miniCalendarNext');
            const form = document.getElementById('miniCalendarEventForm');
            const input = document.getElementById('miniCalendarEventInput');
            if (!grid || !form || !input) return;

            miniCalendarCursor = new Date();
            miniCalendarCursor = new Date(miniCalendarCursor.getFullYear(), miniCalendarCursor.getMonth(), 1);
            miniCalendarSelectedIso = toDateKey(new Date());
            miniCalendarEventsState = readCalendarEventsState();

            if (prev) {
                prev.addEventListener('click', () => {
                    miniCalendarCursor = new Date(miniCalendarCursor.getFullYear(), miniCalendarCursor.getMonth() - 1, 1);
                    renderMiniCalendar();
                });
            }

            if (next) {
                next.addEventListener('click', () => {
                    miniCalendarCursor = new Date(miniCalendarCursor.getFullYear(), miniCalendarCursor.getMonth() + 1, 1);
                    renderMiniCalendar();
                });
            }

            form.addEventListener('submit', event => {
                event.preventDefault();
                if (!miniCalendarSelectedIso) return;

                const text = sanitizeCalendarEventText(input.value);
                if (!text) return;

                const bucket = Array.isArray(miniCalendarEventsState[miniCalendarSelectedIso])
                    ? miniCalendarEventsState[miniCalendarSelectedIso]
                    : [];

                bucket.unshift(text);
                miniCalendarEventsState[miniCalendarSelectedIso] = bucket.slice(0, 20);

                saveCalendarEventsState();
                input.value = '';
                renderMiniCalendarEvents();
            });

            renderMiniCalendar();
            renderMiniCalendarEvents();
            updateLocalTimezoneLabels();
            setInterval(renderMiniCalendar, 1000);
        }

        function toPercent(value) {
            const parsed = Number(value);
            if (!Number.isFinite(parsed)) return null;
            return Math.max(0, Math.min(100, parsed));
        }

        function pushSystemMetric(metricName, value) {
            if (!Object.prototype.hasOwnProperty.call(systemMetricHistory, metricName)) return;
            if (!Number.isFinite(value)) return;

            const bucket = systemMetricHistory[metricName];
            bucket.push(value);
            if (bucket.length > SYSTEM_HISTORY_LIMIT) {
                bucket.shift();
            }
        }

        function setMeterValue(id, value) {
            const meter = document.getElementById(id);
            if (!meter) return;
            meter.style.width = `${Math.max(0, Math.min(100, value))}%`;
        }

        function drawSparkline(canvasId, points, strokeStyle) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !points.length) return;

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            const ratio = window.devicePixelRatio || 1;
            const width = Math.max(180, canvas.clientWidth || canvas.width);
            const height = Math.max(56, canvas.clientHeight || canvas.height);

            canvas.width = Math.floor(width * ratio);
            canvas.height = Math.floor(height * ratio);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

            ctx.clearRect(0, 0, width, height);

            const maxValue = 100;
            const minValue = 0;
            const xStep = points.length > 1 ? width / (points.length - 1) : width;

            ctx.beginPath();
            points.forEach((value, index) => {
                const x = index * xStep;
                const normalized = (value - minValue) / (maxValue - minValue || 1);
                const y = height - (normalized * (height - 8)) - 4;

                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });

            ctx.lineWidth = 2;
            ctx.strokeStyle = strokeStyle;
            ctx.stroke();

            const lastX = (points.length - 1) * xStep;
            const lastNormalized = (points[points.length - 1] - minValue) / (maxValue - minValue || 1);
            const lastY = height - (lastNormalized * (height - 8)) - 4;
            ctx.beginPath();
            ctx.arc(lastX, lastY, 3, 0, Math.PI * 2);
            ctx.fillStyle = strokeStyle;
            ctx.fill();
        }

        function updateSystemCharts(status) {
            const cpu = toPercent(status.cpu_usage_percent);
            const ram = toPercent(status.ram_used_percent);
            const disk = toPercent(status.disk_used_percent);

            if (cpu !== null) {
                pushSystemMetric('cpu', cpu);
                setMeterValue('sys_cpu_bar', cpu);
            }
            if (ram !== null) {
                pushSystemMetric('ram', ram);
                setMeterValue('sys_ram_bar', ram);
            }
            if (disk !== null) {
                pushSystemMetric('disk', disk);
                setMeterValue('sys_disk_bar', disk);
            }

            drawSparkline('sys_cpu_chart', systemMetricHistory.cpu, '#9ac6ff');
            drawSparkline('sys_ram_chart', systemMetricHistory.ram, '#76e0a1');
            drawSparkline('sys_disk_chart', systemMetricHistory.disk, '#ffd27a');
        }

        async function refreshSystemStatus() {
            const list = document.getElementById('systemStatusList');
            if (!list) return;

            try {
                const response = await fetch('/system-status');
                if (!response.ok) return;

                const payload = await response.json();
                const status = payload.status || {};

                document.getElementById('sys_timestamp').textContent = getSystemTimestampLocal(status);
                document.getElementById('sys_cpu').textContent = status.cpu_usage_percent != null ? `${status.cpu_usage_percent}%` : '-';
                document.getElementById('sys_ram').textContent = status.ram_used_percent != null ? `${status.ram_used_percent}%` : '-';
                document.getElementById('sys_ram_mb').textContent = status.ram_used_mb ?? '-';
                document.getElementById('sys_disk').textContent = status.disk_used_percent != null ? `${status.disk_used_percent}%` : '-';
                document.getElementById('sys_webtop').textContent = status.webtop_online ? 'Online' : 'Offline';
                updateLocalTimezoneLabels();
                const webtopPill = document.getElementById('sys_webtop_pill');
                if (webtopPill) {
                    webtopPill.textContent = status.webtop_online ? 'Servicio activo' : 'Servicio no disponible';
                    webtopPill.classList.toggle('is-online', !!status.webtop_online);
                    webtopPill.classList.toggle('is-offline', !status.webtop_online);
                }
                updateSystemCharts(status);
            } catch (error) {
                // Keep last known values when request fails.
            }
        }

        function startGuestCountdown() {
            const label = document.getElementById('guestRemainingLabel');
            if (!label) return;

            let remaining = Number(label.dataset.guestRemaining || 0);

            function render() {
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                const mm = String(minutes).padStart(2, '0');
                const ss = String(seconds).padStart(2, '0');
                label.textContent = `Tiempo restante invitado: ${mm}:${ss}`;
            }

            render();

            const timer = setInterval(() => {
                remaining = Math.max(0, remaining - 1);
                render();

                if (remaining <= 0) {
                    clearInterval(timer);
                    label.textContent = 'Tiempo de invitado expirado. Recarga para continuar.';
                }
            }, 1000);
        }

        async function loadNews(endpoint, listId) {
            const list = document.getElementById(listId);

            try {
                const response = await fetch(endpoint);
                const data = await response.json();

                if (!data.items || !data.items.length) {
                    list.innerHTML = '<li>No se pudieron cargar noticias.</li>';
                    return;
                }

                list.innerHTML = data.items
                    .map(item => `<li><a href="${item.link}" target="_blank" rel="noopener noreferrer">${item.title}</a></li>`)
                    .join('');
            } catch (error) {
                list.innerHTML = '<li>Error cargando noticias.</li>';
            }
        }

        loadNews('/linux-news', 'linux-news-list');
        loadNews('/cyber-news', 'cyber-news-list');
        window.addEventListener('DOMContentLoaded', applySidebarState);
        window.addEventListener('DOMContentLoaded', applyThemeState);
        window.addEventListener('DOMContentLoaded', applySavedGadgetOrder);
        window.addEventListener('DOMContentLoaded', applySavedGadgetSizes);
        window.addEventListener('DOMContentLoaded', enableHomeGadgetDnD);
        window.addEventListener('DOMContentLoaded', applySavedNewsFilter);
        window.addEventListener('DOMContentLoaded', initProductivityWidget);
        window.addEventListener('DOMContentLoaded', initMiniCalendar);
        window.addEventListener('DOMContentLoaded', refreshSystemStatus);
        window.addEventListener('DOMContentLoaded', startGuestCountdown);
        window.addEventListener('resize', () => {
            if (!document.getElementById('systemStatusList')) return;
            drawSparkline('sys_cpu_chart', systemMetricHistory.cpu, '#9ac6ff');
            drawSparkline('sys_ram_chart', systemMetricHistory.ram, '#76e0a1');
            drawSparkline('sys_disk_chart', systemMetricHistory.disk, '#ffd27a');
        });

        if (document.getElementById('systemStatusList')) {
            setInterval(refreshSystemStatus, 5000);
        }
    </script>
</body>
</html>