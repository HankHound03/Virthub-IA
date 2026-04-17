<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido {{ $currentUser['username'] ?? 'Usuario' }}</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
</head>
<body>
    <header>
        <div class="header-controls">
            <div class= "toggleable-sidebar" onclick="toggleMenu(event)" aria-label="Abrir menu" title="Menu">
                <span class="menu-icon" aria-hidden="true"></span>
                <div class="sidebar" onclick="event.stopPropagation()">
                    <button type="button" onclick="location.href='{{ url('/') }}'">Volver a Inicio</button>
                    @if (($currentUser['role'] ?? 'user') === 'admin')
                        <button type="button" onclick="location.href='{{ url('/admin/users') }}'">Panel Admin</button>
                    @endif
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit">Cerrar Sesion</button>
                    </form>
                </div>
            </div>
            <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <span class="theme-icon" aria-hidden="true"></span>
            </div>
        </div>
        <h1>Bienvenido {{ $currentUser['username'] ?? 'Usuario' }}</h1>
        @if (($currentUser['role'] ?? 'user') === 'guest')
            <p class="auth-message auth-success" id="guestRemainingLabel" data-guest-remaining="{{ (int) ($guestRemainingSeconds ?? 0) }}" style="max-width: 360px; margin: 0 auto 10px auto;">
                Tiempo restante invitado: calculando...
            </p>
        @endif
    </header>

    <div class="container-toolbar">
        <button type="button" class="container-load-btn" onclick="loadInIframe(true)">Recargar Contenedor</button>
    </div>
    <iframe id="viewer"></iframe>
    <footer>Codename VirtHub v0.4</footer>
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
        function loadInIframe(force = false) {
            const iframe = document.getElementById('viewer');
            if (!iframe) return;

            if (force || !iframe.src) {
                iframe.src = '/contenedor/launch';
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

        window.addEventListener('DOMContentLoaded', applySidebarState);
        window.addEventListener('DOMContentLoaded', applyThemeState);
        window.addEventListener('DOMContentLoaded', () => loadInIframe());
        window.addEventListener('DOMContentLoaded', startGuestCountdown);
    </script>
</body>
</html>