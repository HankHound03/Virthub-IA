<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Configuracion - {{ $currentUser['username'] ?? 'Usuario' }}</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    <style>
        .config-shell {
            margin: 5px;
            padding: 14px;
            background-color: var(--vh-surface-strong);
            border: 1px solid var(--vh-border);
            backdrop-filter: blur(10px);
            color: var(--vh-panel-text);
            font-family: Monocraft Nerd Font, monospace;
        }

        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .config-card {
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            padding: 12px;
        }

        .config-card h3 {
            margin: 0 0 10px 0;
            color: var(--vh-text);
        }

        .config-note {
            color: var(--vh-text-soft);
            font-size: 12px;
            margin: 0 0 8px 0;
        }

        @media (max-width: 900px) {
            .config-grid {
                grid-template-columns: 1fr;
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
                    <button type="button" onclick="location.href='{{ url('/') }}'">Home</button>
                    <button type="button" onclick="location.href='{{ url('/foro') }}'">Foro</button>
                    <button type="button" onclick="location.href='{{ url('/contenedor') }}'">Contenedor</button>
                    @if (($currentUser['role'] ?? 'user') === 'admin')
                        <button type="button" onclick="location.href='{{ url('/admin/users') }}'">Panel Admin</button>
                    @endif
                </div>
            </div>
            <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <span class="theme-icon" aria-hidden="true"></span>
            </div>
        </div>

        @php
            $profileImage = (string) ($currentUser['profile_image_path'] ?? '');
            $frameColor = (string) ($currentUser['profile_frame_color'] ?? '#6ea8ff');
            $userInitial = strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1));
        @endphp

        <div class="header-profile-dock toggleable-profile-menu" onclick="toggleProfileMenu(event)" title="Menu de perfil" aria-label="Menu de perfil">
            <div class="profile-aero-frame profile-aero-frame-sm" id="headerProfileFramePreview" style="--profile-frame-color: {{ $frameColor }};">
                @if ($profileImage !== '')
                    <img src="{{ asset($profileImage) }}" alt="Foto de perfil de {{ $currentUser['username'] }}" loading="lazy">
                @else
                    <span>{{ $userInitial }}</span>
                @endif
            </div>
            <div class="profile-menu" onclick="event.stopPropagation()">
                <button type="button" onclick="location.href='{{ url('/configuracion') }}'">Configuracion</button>
                <form method="POST" action="/logout">
                    @csrf
                    <button type="submit">Cerrar Sesion</button>
                </form>
            </div>
        </div>

        <h1>Configuracion de Cuenta</h1>
    </header>

    @include('partials.chat-widget')

    <div class="config-shell">
        @if (session('error'))
            <p class="auth-message auth-error">{{ session('error') }}</p>
        @endif

        @if (session('success'))
            <p class="auth-message auth-success">{{ session('success') }}</p>
        @endif

        <div class="config-grid">
            <section class="config-card">
                <h3>Foto y Marco Aero</h3>
                <p class="config-note">Puedes subir una nueva foto y ajustar el color del marco estilo Aero.</p>
                <div class="profile-area-card">
                    <div class="profile-aero-frame" id="profileFramePreview" style="--profile-frame-color: {{ $frameColor }};">
                        @if ($profileImage !== '')
                            <img src="{{ asset($profileImage) }}" alt="Foto de perfil de {{ $currentUser['username'] }}" loading="lazy">
                        @else
                            <span>{{ $userInitial }}</span>
                        @endif
                    </div>
                    <div class="profile-aero-meta">
                        <strong>{{ $currentUser['username'] }}</strong>
                        <small>Previsualizacion del marco</small>
                    </div>
                </div>

                <form method="POST" action="/profile/appearance" enctype="multipart/form-data" class="profile-form-block">
                    @csrf
                    <label>
                        Color del marco
                        <input type="color" name="frame_color" id="frameColorInput" value="{{ $frameColor }}" aria-label="Color del marco de perfil">
                    </label>
                    <label>
                        Foto de perfil (opcional)
                        <input type="file" name="profile_image" accept="image/png,image/jpeg,image/webp,image/gif">
                    </label>
                    <button type="submit">Guardar perfil</button>
                </form>
            </section>

            <section class="config-card">
                <h3>Seguridad</h3>
                <p class="config-note">Para cambiar tu contrasena, primero valida tu contrasena actual.</p>
                <form method="POST" action="/profile/password" class="profile-form-block">
                    @csrf
                    <label>
                        Contrasena actual
                        <input type="password" name="current_password" required>
                    </label>
                    <label>
                        Nueva contrasena
                        <input type="password" name="new_password" required>
                    </label>
                    <label>
                        Repite la nueva contrasena
                        <input type="password" name="new_password_confirmation" required>
                    </label>
                    <button type="submit">Cambiar mi contrasena</button>
                </form>
            </section>
        </div>
    </div>

    <footer>Codename Virthub v0.7b</footer>

    <script>
        function getUserKey() {
            return @json($currentUser['username'] ?? 'guest');
        }

        function getThemeStorageKey() {
            return 'virthub_dark_mode_' + getUserKey();
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

        function bindFrameColorLivePreview() {
            const input = document.getElementById('frameColorInput');
            if (!input) return;

            const previews = [
                document.getElementById('profileFramePreview'),
                document.getElementById('headerProfileFramePreview')
            ].filter(Boolean);

            if (previews.length === 0) return;

            const applyColor = (value) => {
                previews.forEach(preview => {
                    preview.style.setProperty('--profile-frame-color', value);
                });
            };

            input.addEventListener('input', (event) => {
                applyColor(event.target.value || '#6ea8ff');
            });

            applyColor(input.value || '#6ea8ff');
        }

        window.addEventListener('DOMContentLoaded', applySidebarState);
        window.addEventListener('DOMContentLoaded', applyThemeState);
        window.addEventListener('DOMContentLoaded', bindFrameColorLivePreview);
    </script>
</body>
</html>
