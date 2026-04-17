<!DOCTYPE html>
<html lang="es">
<style></style>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VirtHub</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
</head>
<body>
    <header>
        <div class="header-controls">
            <div class= "toggleable-sidebar" onclick="toggleMenu(event)" aria-label="Abrir menu" title="Menu">
                <span class="menu-icon" aria-hidden="true"></span>
                <div class="sidebar" onclick="event.stopPropagation()">
                    <button onclick="location.href='{{ url('/') }}'">Home</button>
                    <button onclick="window.open('https://github.com/FrankMon03/Virthub-IA', '_blank')">GitHub Project</button>
                </div>
            </div>
            <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <span class="theme-icon" aria-hidden="true"></span>
            </div>
        </div>
        <h1>VirtHub</h1>
    </header>

    <div class="home-panels" id="gadgetBoard">
        <aside class="linux-news-panel gadget" data-gadget-id="news">
            <div class="gadget-head">
                <h3>Noticias</h3>
                <div class="gadget-actions">
                    <button type="button" class="gadget-mini-btn" onclick="moveGadget('news', -1)">↑</button>
                    <button type="button" class="gadget-mini-btn" onclick="moveGadget('news', 1)">↓</button>
                </div>
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

        <div class="login-screen gadget access-gadget" data-gadget-id="access">
            <div class="gadget-head">
                <h3>Acceso</h3>
                <div class="gadget-actions">
                    <button type="button" class="gadget-mini-btn" onclick="moveGadget('access', -1)">↑</button>
                    <button type="button" class="gadget-mini-btn" onclick="moveGadget('access', 1)">↓</button>
                </div>
            </div>

            <p class="access-intro">
                Accede rápido a la parte principal del sistema, o entra como invitado si solo necesitas una sesión temporal.
            </p>

            @if (session('error'))
                <p class="auth-message auth-error">{{ session('error') }}</p>
            @endif

            @if (session('success'))
                <p class="auth-message auth-success">{{ session('success') }}</p>
            @endif

            @if (!empty($currentUser))
                <p class="auth-message auth-success">
                    Sesion activa: {{ $currentUser['username'] }} ({{ $currentUser['role'] }})
                </p>

                @if (($currentUser['role'] ?? 'user') === 'guest')
                    <p class="auth-message auth-success" id="guestRemainingLabel" data-guest-remaining="{{ (int) ($guestRemainingSeconds ?? 0) }}">
                        Tiempo restante invitado: calculando...
                    </p>
                @endif

                <form method="POST" action="/logout" id="logoutForm" class="hidden-submit-form">
                    @csrf
                    <input type="hidden" name="_silent" value="1">
                </form>

                <div class="quick-links-grid access-links-grid">
                    <button type="button" onclick="location.href='{{ url('/contenedor') }}'">Contenedor</button>
                    @if (($currentUser['role'] ?? 'user') === 'admin')
                        <button type="button" onclick="location.href='{{ url('/admin/users') }}'">Panel Admin</button>
                    @endif
                    <button type="button" onclick="document.getElementById('logoutForm')?.submit()">Cerrar Sesión</button>
                </div>
            @else
                <form method="POST" action="/login" id="loginForm">
                    @csrf
                    <label for="username">Usuario<p><input type="text" name="username" id="username" value="{{ old('username') }}" required><p></label>
                    <label for="password">Contrasena<p><input type="password" name="password" id="password" required onkeypress="if(event.key==='Enter') document.getElementById('loginForm').requestSubmit();"><p></label>
                    <button type="submit" style="display:none;">Enviar</button>
                </form>

                <form method="POST" action="/guest-login" id="guestLoginForm">
                    @csrf
                </form>

                <div class="quick-links-grid access-links-grid">
                    <button type="button" onclick="document.getElementById('loginForm')?.requestSubmit()">Iniciar Sesión</button>
                    <button type="button" onclick="document.getElementById('guestLoginForm')?.submit()">Invitado</button>
                </div>
            @endif
        </div>

        @if (!empty($currentUser) && ($currentUser['role'] ?? 'user') === 'admin')
            <aside class="linux-news-panel gadget system-status-panel" data-gadget-id="system">
                <div class="gadget-head">
                    <h3>Estado del Sistema</h3>
                    <div class="gadget-actions">
                        <button type="button" class="gadget-mini-btn" onclick="moveGadget('system', -1)">↑</button>
                        <button type="button" class="gadget-mini-btn" onclick="moveGadget('system', 1)">↓</button>
                    </div>
                </div>

                <ul class="system-status-list" id="systemStatusList">
                    <li>Hora: <strong id="sys_timestamp">{{ $systemStatus['timestamp'] ?? '-' }}</strong></li>
                    <li>CPU en uso: <strong id="sys_cpu">{{ isset($systemStatus['cpu_usage_percent']) ? $systemStatus['cpu_usage_percent'] . '%' : '-' }}</strong></li>
                    <li>RAM en uso: <strong id="sys_ram">{{ isset($systemStatus['ram_used_percent']) ? $systemStatus['ram_used_percent'] . '%' : '-' }}</strong></li>
                    <li>RAM usada MB: <strong id="sys_ram_mb">{{ $systemStatus['ram_used_mb'] ?? '-' }}</strong></li>
                    <li>Disco usado: <strong id="sys_disk">{{ isset($systemStatus['disk_used_percent']) ? $systemStatus['disk_used_percent'] . '%' : '-' }}</strong></li>
                    <li>WebTop: <strong id="sys_webtop">{{ !empty($systemStatus['webtop_online']) ? 'Online' : 'Offline' }}</strong></li>
                </ul>
            </aside>
        @endif
    </div>
    <footer>Codename VirtHub v0.4</footer>
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

        function saveGadgetOrder() {
            const board = document.getElementById('gadgetBoard');
            if (!board) return;

            const order = Array.from(board.querySelectorAll('.gadget')).map(g => g.dataset.gadgetId);
            localStorage.setItem(getGadgetOrderKey(), JSON.stringify(order));
        }

        function applySavedGadgetOrder() {
            const board = document.getElementById('gadgetBoard');
            if (!board) return;

            const raw = localStorage.getItem(getGadgetOrderKey());
            if (!raw) return;

            try {
                const order = JSON.parse(raw);
                if (!Array.isArray(order)) return;

                order.forEach(id => {
                    const gadget = board.querySelector(`.gadget[data-gadget-id="${id}"]`);
                    if (gadget) {
                        board.appendChild(gadget);
                    }
                });
            } catch (error) {
                // Ignore invalid local storage payloads.
            }
        }

        function moveGadget(gadgetId, direction) {
            const board = document.getElementById('gadgetBoard');
            if (!board) return;

            const gadget = board.querySelector(`.gadget[data-gadget-id="${gadgetId}"]`);
            if (!gadget) return;

            const gadgets = Array.from(board.querySelectorAll('.gadget'));
            const index = gadgets.indexOf(gadget);
            const newIndex = index + direction;

            if (newIndex < 0 || newIndex >= gadgets.length) return;

            if (direction < 0) {
                board.insertBefore(gadget, gadgets[newIndex]);
            } else {
                const ref = gadgets[newIndex].nextSibling;
                board.insertBefore(gadget, ref);
            }

            saveGadgetOrder();
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

        async function refreshSystemStatus() {
            const list = document.getElementById('systemStatusList');
            if (!list) return;

            try {
                const response = await fetch('/system-status');
                if (!response.ok) return;

                const payload = await response.json();
                const status = payload.status || {};

                document.getElementById('sys_timestamp').textContent = status.timestamp ?? '-';
                document.getElementById('sys_cpu').textContent = status.cpu_usage_percent != null ? `${status.cpu_usage_percent}%` : '-';
                document.getElementById('sys_ram').textContent = status.ram_used_percent != null ? `${status.ram_used_percent}%` : '-';
                document.getElementById('sys_ram_mb').textContent = status.ram_used_mb ?? '-';
                document.getElementById('sys_disk').textContent = status.disk_used_percent != null ? `${status.disk_used_percent}%` : '-';
                document.getElementById('sys_webtop').textContent = status.webtop_online ? 'Online' : 'Offline';
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
        window.addEventListener('DOMContentLoaded', applySavedNewsFilter);
        window.addEventListener('DOMContentLoaded', refreshSystemStatus);
        window.addEventListener('DOMContentLoaded', startGuestCountdown);

        if (document.getElementById('systemStatusList')) {
            setInterval(refreshSystemStatus, 15000);
        }
    </script>
</body>
</html>