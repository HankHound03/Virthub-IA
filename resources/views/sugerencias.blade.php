<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buzon de Sugerencias - VirtHub</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    <style>
        .suggestions-shell {
            margin: 5px;
            padding: 14px;
            background-color: var(--vh-surface-strong);
            border: 1px solid var(--vh-border);
            backdrop-filter: blur(10px);
        }

        .suggestions-card {
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            padding: 14px;
            color: var(--vh-panel-text);
            font-family: Monocraft Nerd Font, monospace;
            max-width: 900px;
            margin: 0 auto;
        }

        .suggestions-card h2 {
            margin: 0 0 10px 0;
            color: var(--vh-text);
        }

        .suggestions-lead {
            margin: 0 0 8px 0;
            color: var(--vh-text-soft);
            line-height: 1.5;
        }

        .suggestions-note {
            margin: 0 0 14px 0;
            padding: 9px;
            border: 1px dashed var(--vh-border);
            background-color: rgba(0, 0, 0, 0.12);
            color: var(--vh-text-soft);
            line-height: 1.4;
            font-size: 13px;
        }

        .suggestions-form label {
            display: block;
            margin-bottom: 9px;
            color: var(--vh-text-soft);
            font-size: 13px;
        }

        .suggestions-form textarea {
            width: 100%;
            min-height: 170px;
            resize: vertical;
            box-sizing: border-box;
            background-color: var(--vh-button-bg);
            border: 1px solid var(--vh-border);
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
            font-size: 13px;
            padding: 8px;
            margin-top: 6px;
        }

        .mode-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin: 10px 0 12px;
        }

        .mode-option {
            display: flex;
            align-items: center;
            gap: 7px;
            border: 1px solid var(--vh-border);
            background-color: rgba(0, 0, 0, 0.10);
            padding: 9px;
            color: var(--vh-text-soft);
            cursor: pointer;
        }

        .mode-option input {
            margin: 0;
        }

        .suggestions-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .suggestions-actions button,
        .suggestions-actions a {
            padding: 8px 12px;
            background-color: var(--vh-button-bg);
            border: 1px solid var(--vh-border);
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
            text-decoration: none;
            cursor: pointer;
        }

        .suggestions-actions button:hover,
        .suggestions-actions a:hover {
            background-color: var(--vh-button-hover);
        }

        .feedback-msg {
            margin: 0 0 12px 0;
            padding: 9px;
            border: 1px solid var(--vh-border);
            background-color: rgba(0, 0, 0, 0.12);
            color: var(--vh-text-soft);
        }

        .feedback-msg.error {
            border-color: rgba(255, 120, 120, 0.45);
            color: #ffd3d3;
        }

        @media (max-width: 980px) {
            .suggestions-shell {
                margin: 3px;
                padding: 10px;
            }

            .suggestions-card {
                padding: 12px;
            }
        }

        @media (max-width: 780px) {
            .mode-grid {
                grid-template-columns: 1fr;
            }

            .suggestions-actions button,
            .suggestions-actions a {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 560px) {
            .suggestions-shell {
                margin: 0;
                padding: 8px;
            }

            .suggestions-card {
                padding: 10px;
            }

            .suggestions-card h2 {
                font-size: 18px;
            }

            .suggestions-lead,
            .suggestions-note,
            .suggestions-form label,
            .feedback-msg {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-controls">
            <div class="toggleable-sidebar" onclick="toggleMenu(event)" aria-label="Abrir menu" title="Menu">
                <span class="menu-icon" aria-hidden="true"></span>
                <div class="sidebar" onclick="event.stopPropagation()">
                    @include('partials.navigation-menu', ['currentUser' => $currentUser ?? null, 'currentPage' => 'sugerencias'])
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
                    <form method="POST" action="{{ url('/logout') }}">
                        @csrf
                        <button type="submit">Cerrar Sesion</button>
                    </form>
                </div>
            </div>
        @endif

        <h1>Buzon de Sugerencias</h1>
    </header>

    @include('partials.chat-widget')

    <section class="suggestions-shell">
        <div class="suggestions-card">
            @if (session('success'))
                <p class="feedback-msg">{{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <p class="feedback-msg error">Revisa el formulario e intenta de nuevo.</p>
            @endif

            <h2>Tu opinion nos ayuda a mejorar</h2>
            <p class="suggestions-lead">
                Comparte ideas, reportes o mejoras que te gustaria ver en VirtHub.
                Puedes enviarlas de forma anonima o mostrando el usuario que tienes en sesion.
            </p>
            <p class="suggestions-note">
                Leemos y valoramos cada sugerencia. Aunque no todas podran entrar de inmediato,
                si las tomamos en cuenta para las proximas actualizaciones y prioridades del proyecto.
            </p>

            <form method="POST" action="{{ url('/sugerencias') }}" class="suggestions-form">
                @csrf

                @php
                    $canIdentifySuggestionAuthor = !empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest');
                @endphp

                <label>
                    Elige como quieres enviar tu sugerencia:
                </label>

                <div class="mode-grid">
                    <label class="mode-option">
                        <input type="radio" name="author_mode" value="anonymous" {{ (!$canIdentifySuggestionAuthor || old('author_mode', 'anonymous') === 'anonymous') ? 'checked' : '' }}>
                        <span>Anonimo</span>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="author_mode" value="identified" {{ old('author_mode') === 'identified' && $canIdentifySuggestionAuthor ? 'checked' : '' }} {{ $canIdentifySuggestionAuthor ? '' : 'disabled' }}>
                        <span>
                            Mostrar usuario
                            @if (!empty($currentUser['username']))
                                ({{ $currentUser['username'] }})
                            @else
                                (visitante)
                            @endif
                        </span>
                    </label>
                </div>

                @if (!$canIdentifySuggestionAuthor)
                    <p class="suggestions-note">Las cuentas Visitante/Invitado solo pueden enviar sugerencias en modo anonimo.</p>
                @endif

                <label for="message">
                    Sugerencia
                    <textarea name="message" id="message" maxlength="2000" required>{{ old('message') }}</textarea>
                </label>

                <div class="suggestions-actions">
                    <button type="submit">Enviar sugerencia</button>
                    <a href="{{ url('/') }}">Volver a Home</a>
                </div>
            </form>
        </div>
    </section>

    <footer>Codename Virthub 0.9c PreRelease</footer>

    <script>
        function getThemeStorageKey() {
            return 'virthub_dark_mode_' + @json($currentUser['username'] ?? 'guest');
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

        document.addEventListener('click', function (event) {
            const sidebarLauncher = document.querySelector('.toggleable-sidebar');
            if (sidebarLauncher && !sidebarLauncher.contains(event.target)) {
                sidebarLauncher.classList.remove('is-open');
            }

            const profileLauncher = document.querySelector('.toggleable-profile-menu');
            if (profileLauncher && !profileLauncher.contains(event.target)) {
                profileLauncher.classList.remove('is-open');
            }
        });

        applyThemeState();
    </script>
</body>
</html>
