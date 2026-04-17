<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Usuarios</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    <style>
        .admin-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 5px;
            min-height: calc(100vh - 260px);
        }

        .admin-card {
            background-color: rgba(117, 225, 160, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(117, 225, 160, 0.22);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05);
            padding: 20px;
            color: #e8fff3;
            font-family: Monocraft Nerd Font, monospace;
            overflow: auto;
        }

        .admin-card h2 {
            text-align: center;
            margin-top: 0;
            color: #d8ffea;
        }

        .admin-card label {
            display: block;
            margin-bottom: 10px;
        }

        .admin-card input,
        .admin-card select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            border-radius: 8px;
            border: 1px solid rgba(117, 225, 160, 0.35);
            background-color: rgba(127, 255, 212, 0.2);
            color: #ffffff;
            box-sizing: border-box;
            font-family: Monocraft Nerd Font, monospace;
        }

        .admin-card button {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            border: none;
            border-radius: 10px;
            background-color: rgba(127, 255, 212, 0.85);
            color: #0b1b2d;
            font-family: Monocraft Nerd Font, monospace;
            cursor: pointer;
        }

        .admin-card button:hover {
            background-color: rgba(0, 134, 90, 0.6);
            color: #ffffff;
        }

        .admin-message {
            padding: 8px 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            font-size: 14px;
        }

        .admin-error {
            background: rgba(255, 120, 120, 0.2);
            color: #ffd9d9;
        }

        .admin-success {
            background: rgba(117, 225, 160, 0.2);
            color: #d7ffea;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .users-table th,
        .users-table td {
            border-bottom: 1px solid rgba(117, 225, 160, 0.3);
            padding: 8px;
            text-align: left;
        }

        .users-table th {
            color: #9bf2c1;
        }

        .credentials-dropdown {
            display: none;
            margin-top: 12px;
            padding: 12px;
            background-color: rgba(117, 225, 160, 0.15);
            border: 1px solid rgba(117, 225, 160, 0.35);
            border-radius: 8px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
        }

        .credentials-dropdown.show {
            display: block;
        }

        .credentials-dropdown select {
            width: 100%;
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 8px;
            border: 1px solid rgba(117, 225, 160, 0.35);
            background-color: rgba(127, 255, 212, 0.15);
            color: #ffffff;
            box-sizing: border-box;
            font-family: Monocraft Nerd Font, monospace;
            font-size: 12px;
            cursor: pointer;
        }

        .credentials-dropdown button {
            width: 100%;
            padding: 8px;
            border: none;
            border-radius: 8px;
            background-color: rgba(255, 193, 7, 0.7);
            color: #0b1b2d;
            font-family: Monocraft Nerd Font, monospace;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .credentials-dropdown button:hover {
            background-color: rgba(255, 193, 7, 0.95);
        }

        .copy-notification {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: rgba(117, 225, 160, 0.9);
            color: #0b1b2d;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            font-family: Monocraft Nerd Font, monospace;
            animation: slideIn 0.3s ease-in-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(90, 90, 90, 0.82);
            z-index: 9998;
            align-items: center;
            justify-content: center;
        }

        .confirmation-modal.show {
            display: flex;
        }

        .modal-content {
            background-color: rgba(20, 30, 50, 0.95);
            border: 2px solid rgba(255, 100, 100, 0.5);
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            text-align: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        .modal-content h3 {
            color: #ff6464;
            margin: 0 0 15px 0;
            font-size: 18px;
        }

        .modal-content p {
            color: #e8fff3;
            margin: 10px 0 20px 0;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn-cancel {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            background-color: rgba(127, 255, 212, 0.6);
            color: #0b1b2d;
            cursor: pointer;
            font-family: Monocraft Nerd Font, monospace;
            font-weight: bold;
            transition: 0.2s;
        }

        .modal-btn-cancel:hover {
            background-color: rgba(127, 255, 212, 0.9);
        }

        .modal-btn-confirm {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            background-color: rgba(255, 100, 100, 0.8);
            color: #ffffff;
            cursor: pointer;
            font-family: Monocraft Nerd Font, monospace;
            font-weight: bold;
            transition: 0.2s;
        }

        .modal-btn-confirm:hover {
            background-color: rgba(255, 100, 100, 0.95);
        }

        .user-status {
            font-size: 12px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .status-active {
            background-color: rgba(117, 225, 160, 0.4);
            color: #7fe1a0;
        }

        .status-inactive {
            background-color: rgba(255, 120, 120, 0.4);
            color: #ffd9d9;
        }

        .user-actions {
            display: flex;
            gap: 6px;
            justify-content: center;
        }

        .user-actions button {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            font-family: Monocraft Nerd Font, monospace;
            transition: 0.2s;
        }

        .btn-deactivate {
            background-color: rgba(255, 193, 7, 0.7);
            color: #0b1b2d;
        }

        .btn-deactivate:hover {
            background-color: rgba(255, 193, 7, 0.95);
        }

        .btn-activate {
            background-color: rgba(117, 225, 160, 0.75);
            color: #0b1b2d;
        }

        .btn-activate:hover {
            background-color: rgba(117, 225, 160, 0.95);
        }

        .btn-delete {
            background-color: rgba(255, 100, 100, 0.7);
            color: #ffffff;
        }

        .btn-delete:hover {
            background-color: rgba(255, 100, 100, 0.95);
        }

        body:not(.dark-mode) .admin-card {
            background-color: var(--vh-surface-strong);
            border-color: var(--vh-border);
            color: var(--vh-text);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
        }

        body:not(.dark-mode) .admin-card h2,
        body:not(.dark-mode) .users-table th,
        body:not(.dark-mode) .modal-content p {
            color: var(--vh-text);
        }

        body:not(.dark-mode) .admin-card input,
        body:not(.dark-mode) .admin-card select,
        body:not(.dark-mode) .credentials-dropdown select {
            background-color: var(--vh-button-bg);
            border-color: var(--vh-border);
            color: var(--vh-text);
        }

        body:not(.dark-mode) .admin-card button {
            background-color: var(--vh-button-bg);
            border: 1px solid var(--vh-border);
            color: var(--vh-text);
        }

        body:not(.dark-mode) .admin-card button:hover {
            background-color: var(--vh-button-hover);
            color: var(--vh-text);
        }

        body:not(.dark-mode) .users-table th,
        body:not(.dark-mode) .users-table td,
        body:not(.dark-mode) .reports-table th,
        body:not(.dark-mode) .reports-table td {
            border-bottom-color: var(--vh-border);
        }

        body:not(.dark-mode) .credentials-dropdown {
            background-color: rgba(7, 20, 33, 0.38);
            border-color: var(--vh-border);
        }

        body:not(.dark-mode) .report-post-snippet {
            color: var(--vh-text-soft);
        }

        body:not(.dark-mode) .modal-content {
            background-color: rgba(7, 20, 33, 0.92);
            border-color: rgba(255, 120, 120, 0.45);
        }

        body:not(.dark-mode) .admin-link-btn {
            background-color: var(--vh-button-bg);
            color: var(--vh-text);
            border: 1px solid var(--vh-border);
        }

        body:not(.dark-mode) .admin-link-btn:hover {
            background-color: var(--vh-button-hover);
            color: var(--vh-text);
        }

        body.dark-mode .admin-card {
            background-color: rgba(10, 14, 24, 0.64);
            border-color: rgba(255, 255, 255, 0.08);
            color: #eef9ff;
        }

        body.dark-mode .admin-card h2,
        body.dark-mode .users-table th,
        body.dark-mode .modal-content p {
            color: #eef9ff;
        }

        body.dark-mode .admin-card input,
        body.dark-mode .admin-card select {
            background-color: rgba(29, 42, 66, 0.78);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f2fbff;
        }

        body.dark-mode .admin-card button {
            background-color: rgba(71, 103, 150, 0.82);
            color: #f2fbff;
        }

        body.dark-mode .admin-card button:hover {
            background-color: rgba(99, 136, 191, 0.92);
            color: #ffffff;
        }

        body.dark-mode .admin-success {
            background: rgba(117, 225, 160, 0.14);
            color: #d8ffea;
        }

        body.dark-mode .admin-error {
            background: rgba(255, 120, 120, 0.14);
            color: #ffd9d9;
        }

        body.dark-mode .users-table th,
        body.dark-mode .users-table td {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        body.dark-mode .credentials-dropdown {
            background-color: rgba(10, 14, 24, 0.66);
            border-color: rgba(255, 255, 255, 0.08);
        }

        body.dark-mode .credentials-dropdown select {
            background-color: rgba(29, 42, 66, 0.78);
            color: #f2fbff;
        }

        body.dark-mode .modal-content {
            background-color: rgba(8, 12, 20, 0.95);
            border-color: rgba(255, 120, 120, 0.45);
        }

        body.dark-mode .copy-notification {
            background-color: rgba(25, 36, 52, 0.95);
            color: #f2fbff;
        }

        body.dark-mode .user-status.status-active {
            background-color: rgba(117, 225, 160, 0.18);
            color: #9ff0b8;
        }

        body.dark-mode .user-status.status-inactive {
            background-color: rgba(255, 120, 120, 0.18);
            color: #ffb8b8;
        }
        .admin-top-actions {
            margin-bottom: 10px;
            display: flex;
            gap: 10px;
        }

        .admin-top-actions form,
        .admin-top-actions button {
            width: 100%;
        }

        .admin-full-width {
            grid-column: 1 / -1;
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .reports-table th,
        .reports-table td {
            border-bottom: 1px solid rgba(117, 225, 160, 0.3);
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        .reports-table th {
            color: #9bf2c1;
            font-size: 12px;
        }

        .report-reason {
            max-width: 280px;
            white-space: normal;
        }

        .report-post-snippet {
            color: #d7ffea;
            font-size: 12px;
            line-height: 1.4;
            max-width: 420px;
            white-space: normal;
        }

        .admin-link-btn {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 8px;
            text-decoration: none;
            background-color: rgba(127, 255, 212, 0.75);
            color: #0b1b2d;
            font-size: 12px;
        }

        .admin-link-btn:hover {
            background-color: rgba(127, 255, 212, 0.95);
            color: #0b1b2d;
        }

        .report-delete-form {
            margin-top: 6px;
        }

        .report-delete-btn {
            width: 100%;
            padding: 6px 10px;
            border: none;
            border-radius: 8px;
            background-color: rgba(255, 100, 100, 0.78);
            color: #ffffff;
            font-family: Monocraft Nerd Font, monospace;
            font-size: 12px;
            cursor: pointer;
        }

        .report-delete-btn:hover {
            background-color: rgba(255, 100, 100, 0.95);
        }

        body.dark-mode .reports-table th,
        body.dark-mode .reports-table td {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        body.dark-mode .report-post-snippet {
            color: #cfe4ff;
        }

        body.dark-mode .admin-link-btn {
            background-color: rgba(99, 136, 191, 0.9);
            color: #f2fbff;
        }

        body.dark-mode .admin-link-btn:hover {
            background-color: rgba(120, 158, 214, 0.95);
            color: #ffffff;
        }

        body.dark-mode .report-delete-btn {
            background-color: rgba(198, 88, 88, 0.85);
        }

        body.dark-mode .report-delete-btn:hover {
            background-color: rgba(222, 102, 102, 0.95);
        }

        @media (max-width: 900px) {
            .admin-wrapper {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            header h1 {
                font-size: clamp(24px, 8vw, 46px);
                line-height: 1.15;
                word-break: break-word;
                margin: 6px 0 0;
                text-align: center;
            }

            .admin-card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-controls">
            <div class= "toggleable-sidebar" onclick="toggleMenu(event)" aria-label="Abrir menu" title="Menu">
                <span class="menu-icon" aria-hidden="true"></span>
                <div class="sidebar" onclick="event.stopPropagation()">
                    <button onclick="location.href='{{ url('/') }}'">Volver a Inicio</button>
                    <button onclick="location.href='{{ url('/foro') }}'">Foro</button>
                    <button onclick="location.href='{{ url('/contenedor') }}'">Contenedor</button>
                </div>
            </div>
            <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <span class="theme-icon" aria-hidden="true"></span>
            </div>
        </div>
        @if (!empty($currentUser) && ($currentUser['role'] ?? 'guest') !== 'guest')
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
                    <button type="button" onclick="location.href='{{ url('/configuracion') }}'">Configuracion</button>
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit">Cerrar Sesion</button>
                    </form>
                </div>
            </div>
        @endif
        <h1>Admin de Usuarios - {{ $currentUser['username'] ?? 'admin' }}</h1>
    </header>

    @include('partials.chat-widget')


    <div class="admin-wrapper">
        <section class="admin-card">
            <h2>Crear Usuario</h2>

            @if (session('error'))
                <div class="admin-message admin-error">{{ session('error') }}</div>
            @endif

            @if (session('success'))
                <div class="admin-message admin-success" id="successMessage">{{ session('success') }}</div>
                <div class="credentials-dropdown" id="credentialsDropdown">
                    <label>
                        Credenciales Generadas
                        <select id="credentialsSelect">
                            <option value="">-- Selecciona para copiar --</option>
                        </select>
                    </label>
                    <button type="button" onclick="copySelected()">📋 Copiar al Portapapeles</button>
                </div>
            @endif

            <form method="POST" action="/admin/users">
                @csrf
                <label>
                    Username (opcional si aleatorio)
                    <input type="text" name="username" value="{{ old('username') }}" placeholder="ej: frank_user">
                </label>

                <label>
                    Password inicial (opcional si aleatorio)
                    <input type="password" name="password" placeholder="ej: Seg1234!">
                </label>

                <label>
                    Rol
                    <select name="role" id="create_role">
                        <option value="user">user</option>
                        <option value="admin">admin</option>
                    </select>
                </label>

                <label>
                    <input type="checkbox" name="random_username" id="random_username" value="1"> Generar username aleatorio
                </label>

                <label>
                    <input type="checkbox" name="random_password" id="random_password" value="1"> Generar password aleatorio
                </label>

                <p id="admin-credential-note" style="display:none; margin: 4px 0 10px 0; font-size: 12px; color: #ffd9d9;">
                    Para rol admin, username y password deben ser manuales.
                </p>

                <button type="submit">Crear Usuario</button>
            </form>

            <hr style="margin: 18px 0; border-color: rgba(117, 225, 160, 0.2);">

            <h2 style="margin-top: 0;">Cambiar Password</h2>

            <form method="POST" action="/admin/users/password">
                @csrf
                <label>
                    Username existente
                    <input type="text" name="username" placeholder="ej: admin" required>
                </label>

                <label>
                    Nuevo password
                    <input type="password" name="new_password" required>
                </label>

                <button type="submit">Actualizar Password</button>
            </form>
        </section>

        <section class="admin-card">
            <h2>Usuarios Registrados</h2>

            <table class="users-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Rol</th>
                        <th>Ultima conexion</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user['username'] }}</td>
                            <td>{{ $user['role'] }}</td>
                            <td>{{ $user['last_login_at'] ?? 'Nunca' }}</td>
                            <td>
                                @if ($user['is_active'])
                                    <span class="user-status status-active">✓ Activo</span>
                                @else
                                    <span class="user-status status-inactive">✗ Desactivado</span>
                                @endif
                            </td>
                            <td>
                                <div class="user-actions">
                                    @if ($user['is_active'])
                                        <button type="button" class="btn-deactivate" onclick="openConfirmation('deactivate', '{{ $user['username'] }}')">🔒 Desactivar</button>
                                    @else
                                        <button type="button" class="btn-activate" onclick="openConfirmation('activate', '{{ $user['username'] }}')">▶ Activar</button>
                                    @endif
                                    <button type="button" class="btn-delete" onclick="openConfirmation('delete', '{{ $user['username'] }}')">🗑️ Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No hay usuarios aun.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="admin-card admin-full-width">
            <h2>Reportes del Foro</h2>

            @if (empty($forumReports ?? []))
                <p>No hay reportes del foro por ahora.</p>
            @else
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Fecha reporte</th>
                            <th>Reportado por</th>
                            <th>Motivo</th>
                            <th>Post</th>
                            <th>Autor post</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($forumReports as $report)
                            <tr>
                                <td>{{ $report['reported_at'] ?: '-' }}</td>
                                <td>{{ $report['reporter'] ?: '-' }}</td>
                                <td class="report-reason">{{ $report['reason'] ?: 'Sin detalle' }}</td>
                                <td>
                                    <div><strong>{{ $report['post_title'] ?: 'Publicacion sin titulo' }}</strong></div>
                                    <div class="report-post-snippet">{{ \Illuminate\Support\Str::limit($report['post_content'] ?: '', 180) }}</div>
                                </td>
                                <td>{{ $report['post_author'] ?: '-' }}</td>
                                <td>
                                    <a class="admin-link-btn" href="{{ url('/foro') }}">Ir al foro</a>
                                    <form class="report-delete-form" method="POST" action="{{ url('/admin/forum-reports/delete') }}">
                                        @csrf
                                        <input type="hidden" name="post_id" value="{{ $report['post_id'] }}">
                                        <input type="hidden" name="report_id" value="{{ $report['report_id'] }}">
                                        <button type="submit" class="report-delete-btn">Reporte verificado</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <footer>Codename Virthub 0.9 PreRelease</footer>

    <div class="confirmation-modal" id="confirmationModal">
        <div class="modal-content">
            <h3 id="modalTitle">Confirmar acción</h3>
            <p id="modalMessage"></p>
            <div class="modal-actions">
                <button class="modal-btn-cancel" onclick="closeConfirmation()">Cancelar</button>
                <button class="modal-btn-confirm" onclick="submitConfirmation()">Confirmar</button>
            </div>
        </div>
    </div>
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

        function toggleProfileMenu(event) {
            if (event) {
                event.stopPropagation();
            }

            const launcher = document.querySelector('.toggleable-profile-menu');
            if (!launcher) return;

            launcher.classList.toggle('is-open');
        }

        function enforceAdminCredentialPolicy() {
            const roleSelect = document.getElementById('create_role');
            const randomUsername = document.getElementById('random_username');
            const randomPassword = document.getElementById('random_password');
            const note = document.getElementById('admin-credential-note');

            if (!roleSelect || !randomUsername || !randomPassword || !note) return;

            const isAdminRole = roleSelect.value === 'admin';

            if (isAdminRole) {
                randomUsername.checked = false;
                randomPassword.checked = false;
            }

            randomUsername.disabled = isAdminRole;
            randomPassword.disabled = isAdminRole;
            note.style.display = isAdminRole ? 'block' : 'none';
            randomUsername.parentElement.style.opacity = isAdminRole ? '0.55' : '1';
            randomPassword.parentElement.style.opacity = isAdminRole ? '0.55' : '1';
        }

        function parseCredentials() {
            const successMsg = document.getElementById('successMessage');
            const dropdown = document.getElementById('credentialsDropdown');
            const select = document.getElementById('credentialsSelect');

            if (!successMsg) return;

            const text = successMsg.textContent;
            const usernameMatch = text.match(/Usuario creado: ([^\s(]+)/);
            const passwordMatch = text.match(/Password: ([^\s]+)/);

            if (usernameMatch && passwordMatch) {
                const username = usernameMatch[1];
                const password = passwordMatch[1];
                const fullCred = username + ':' + password;

                select.innerHTML = `
                    <option value="">-- Selecciona para copiar --</option>
                    <option value="${fullCred}">Username: ${username}</option>
                    <option value="${password}">Password: ${password}</option>
                    <option value="${fullCred}">Ambas: ${username}:${password}</option>
                `;

                dropdown.classList.add('show');
            }
        }

        function copySelected() {
            const select = document.getElementById('credentialsSelect');
            const value = select.value;

            if (!value) {
                showNotification('⚠️ Selecciona una opción primero');
                return;
            }

            navigator.clipboard.writeText(value).then(() => {
                showNotification('✅ Copiado al portapapeles');
                select.value = '';
            }).catch(() => {
                showNotification('❌ Error al copiar');
            });
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'copy-notification';
            notification.textContent = message;
            notification.style.display = 'block';
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                setTimeout(() => notification.remove(), 300);
            }, 2000);
        }

        window.addEventListener('DOMContentLoaded', parseCredentials);

        let currentAction = null;
        let currentUsername = null;

        function openConfirmation(action, username) {
            currentAction = action;
            currentUsername = username;

            const modal = document.getElementById('confirmationModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');

            if (action === 'deactivate') {
                title.textContent = '⚠️ Desactivar Usuario';
                message.textContent = `¿Estás seguro de que deseas desactivar a "${username}"? Podrá ser reactivado después.`;
            } else if (action === 'activate') {
                title.textContent = '▶ Activar Usuario';
                message.textContent = `¿Estás seguro de que deseas activar a "${username}"? Volverá a poder iniciar sesión.`;
            } else if (action === 'delete') {
                title.textContent = '🗑️ Eliminar Usuario';
                message.textContent = `¿Estás seguro de que deseas eliminar a "${username}"? Esta acción NO se puede deshacer.`;
            }

            modal.classList.add('show');
        }

        function closeConfirmation() {
            const modal = document.getElementById('confirmationModal');
            modal.classList.remove('show');
            currentAction = null;
            currentUsername = null;
        }

        function submitConfirmation() {
            if (!currentAction || !currentUsername) return;

            const form = document.createElement('form');
            form.method = 'POST';

            if (currentAction === 'deactivate') {
                form.action = '/admin/users/deactivate';
            } else if (currentAction === 'activate') {
                form.action = '/admin/users/activate';
            } else {
                form.action = '/admin/users/delete';
            }

            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = document.querySelector('input[name="_token"]').value;

            const usernameInput = document.createElement('input');
            usernameInput.type = 'hidden';
            usernameInput.name = 'username';
            usernameInput.value = currentUsername;

            form.appendChild(tokenInput);
            form.appendChild(usernameInput);
            document.body.appendChild(form);
            form.submit();
        }

        document.getElementById('confirmationModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeConfirmation();
            }
        });

        window.addEventListener('click', function() {
            const sidebarLauncher = document.querySelector('.toggleable-sidebar');
            if (sidebarLauncher) {
                sidebarLauncher.classList.remove('is-open');
            }

            const profileLauncher = document.querySelector('.toggleable-profile-menu');
            if (profileLauncher) {
                profileLauncher.classList.remove('is-open');
            }
        });

        window.addEventListener('DOMContentLoaded', applySidebarState);
        window.addEventListener('DOMContentLoaded', applyThemeState);
        window.addEventListener('DOMContentLoaded', enforceAdminCredentialPolicy);
        document.getElementById('create_role')?.addEventListener('change', enforceAdminCredentialPolicy);
    </script>
</body>
</html>
