<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Configuracion - {{ $currentUser['username'] ?? 'Usuario' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
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

        .profile-crop-editor {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid var(--vh-border);
            background-color: rgba(0, 0, 0, 0.12);
        }

        .profile-crop-editor[hidden] {
            display: none;
        }

        .profile-crop-canvas {
            display: block;
            width: min(100%, 320px);
            aspect-ratio: 1;
            margin: 0 auto 10px;
            background: #101820;
            cursor: grab;
            touch-action: none;
        }

        .profile-crop-canvas:active {
            cursor: grabbing;
        }

        .profile-crop-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .profile-crop-controls label {
            flex: 1;
            min-width: 140px;
            margin: 0;
            font-size: 12px;
        }

        .profile-crop-controls input[type="range"] {
            width: 100%;
            margin: 5px 0 0;
            padding: 0;
        }

        .profile-crop-actions {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 1100px) {
            .config-shell {
                margin: 3px;
                padding: 10px;
            }
        }

        @media (max-width: 900px) {
            .config-grid {
                grid-template-columns: 1fr;
            }

            .config-card {
                padding: 10px;
            }

            .profile-area-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        @media (max-width: 560px) {
            .config-shell {
                margin: 0;
                padding: 8px;
            }

            .config-card h3 {
                font-size: 16px;
            }

            .config-note,
            .config-card label {
                font-size: 12px;
            }

            .config-card button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    @php
        $profileImage = (string) ($currentUser['profile_image_path'] ?? '');
        $frameColor = (string) ($currentUser['profile_frame_color'] ?? '#6ea8ff');
        $userInitial = strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1));
    @endphp

    @include('partials.header', [
        'pageTitle' => 'Configuracion de Cuenta',
        'currentUser' => $currentUser ?? null,
        'currentPage' => 'configuracion'
    ])

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
                        <input type="file" name="profile_image" id="profileImageInput" accept="image/png,image/jpeg,image/webp,image/gif">
                    </label>
                    <div class="profile-crop-editor" id="profileCropEditor" hidden>
                        <canvas class="profile-crop-canvas" id="profileCropCanvas" width="320" height="320" aria-label="Vista previa del recorte de la foto"></canvas>
                        <div class="profile-crop-controls">
                            <label>
                                Zoom
                                <input type="range" id="profileCropZoom" min="1" max="3" step="0.01" value="1">
                            </label>
                            <div class="profile-crop-actions">
                                <button type="button" id="profileCropApply">Aplicar recorte</button>
                                <button type="button" id="profileCropCancel">Cancelar</button>
                            </div>
                        </div>
                    </div>
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

                <div class="two-factor-panel">
                    <h4>Autenticacion de dos factores</h4>
                @if (!empty($twoFactorRecoveryCodes = session('two_factor_recovery_codes')))
                    <p class="config-note">Guarda estos codigos. Cada uno puede usarse una sola vez.</p>
                    <pre class="two-factor-recovery">{{ implode("\n", $twoFactorRecoveryCodes) }}</pre>
                @endif

                @if ($twoFactorEnabled)
                    <p class="two-factor-status">2FA activo con Google Authenticator.</p>
                    <form method="POST" action="/security/2fa/recovery-codes" class="profile-form-block" id="recoveryCodesForm">
                        @csrf
                        <label>
                            Contrasena actual para regenerar codigos
                            <input type="password" name="current_password" required>
                        </label>
                        <label>
                            Codigo actual de Authenticator
                            <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                        </label>
                        <button type="submit">Generar nuevos codigos</button>
                        <p class="auth-message" id="recoveryCodesFeedback" hidden role="alert"></p>
                        <pre class="two-factor-recovery" id="recoveryCodesOutput" hidden></pre>
                    </form>
                    <form method="POST" action="/security/2fa/disable" class="profile-form-block">
                        @csrf
                        <label>
                            Contrasena actual
                            <input type="password" name="current_password" required>
                        </label>
                        <label>
                            Codigo actual de Authenticator
                            <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                        </label>
                        <button type="submit">Desactivar 2FA</button>
                    </form>
                @elseif ($twoFactorSetupQr)
                    <p class="config-note">Escanea este codigo con Google Authenticator y confirma el codigo de seis digitos.</p>
                    <div class="two-factor-setup">
                        <img class="two-factor-qr" src="{{ $twoFactorSetupQr }}" alt="Codigo QR para configurar Google Authenticator" width="240" height="240">
                        <form method="POST" action="/security/2fa/confirm" class="profile-form-block" id="twoFactorConfirmForm">
                            @csrf
                            <label>
                                Codigo de confirmacion
                                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                            </label>
                            <button type="submit">Confirmar 2FA</button>
                        </form>
                        <p class="auth-message" id="activationRecoveryFeedback" hidden role="alert"></p>
                        <pre class="two-factor-recovery" id="activationRecoveryCodes" hidden></pre>
                    </div>
                @else
                    <p class="config-note">Protege tu cuenta con un codigo temporal de Google Authenticator.</p>
                    <form method="POST" action="/security/2fa/setup">
                        @csrf
                        <button type="submit">Configurar Google Authenticator</button>
                    </form>
                @endif
                </div>
            </section>
        </div>
    </div>

    <footer>Virthub 1.0</footer>

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

        function getCsrfToken(form) {
            return form.querySelector('input[name="_token"]')?.value || '';
        }

        async function submitJsonForm(form) {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(form)
                },
                body: new FormData(form)
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo completar la solicitud.');
            }

            return payload;
        }

        function bindRecoveryCodesForm() {
            const form = document.getElementById('recoveryCodesForm');
            const feedback = document.getElementById('recoveryCodesFeedback');
            const output = document.getElementById('recoveryCodesOutput');
            if (!form || !feedback || !output) return;

            form.addEventListener('submit', async event => {
                event.preventDefault();
                feedback.hidden = true;

                try {
                    const payload = await submitJsonForm(form);
                    output.textContent = (payload.recovery_codes || []).join('\n');
                    output.hidden = false;
                    feedback.className = 'auth-message auth-success';
                    feedback.textContent = 'Codigos regenerados. Guardalos ahora; cada uno se usa una sola vez.';
                    feedback.hidden = false;
                    form.reset();
                } catch (error) {
                    feedback.className = 'auth-message auth-error';
                    feedback.textContent = error.message;
                    feedback.hidden = false;
                }
            });
        }

        function bindTwoFactorConfirmForm() {
            const form = document.getElementById('twoFactorConfirmForm');
            const feedback = document.getElementById('activationRecoveryFeedback');
            const output = document.getElementById('activationRecoveryCodes');
            if (!form || !feedback || !output) return;

            form.addEventListener('submit', async event => {
                event.preventDefault();
                feedback.hidden = true;

                try {
                    const payload = await submitJsonForm(form);
                    output.textContent = (payload.recovery_codes || []).join('\n');
                    output.hidden = false;
                    feedback.className = 'auth-message auth-success';
                    feedback.textContent = '2FA activado. Guarda estos codigos de recuperacion ahora.';
                    feedback.hidden = false;
                    form.hidden = true;
                } catch (error) {
                    feedback.className = 'auth-message auth-error';
                    feedback.textContent = error.message;
                    feedback.hidden = false;
                }
            });
        }

        function bindFrameColorLivePreview() {
            const input = document.getElementById('frameColorInput');
            if (!input) return;

            const previews = [
                document.getElementById('profileFramePreview')
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

        function bindProfileCropEditor() {
            const input = document.getElementById('profileImageInput');
            const editor = document.getElementById('profileCropEditor');
            const canvas = document.getElementById('profileCropCanvas');
            const zoomInput = document.getElementById('profileCropZoom');
            const applyButton = document.getElementById('profileCropApply');
            const cancelButton = document.getElementById('profileCropCancel');
            const preview = document.getElementById('profileFramePreview');

            if (!input || !editor || !canvas || !zoomInput || !applyButton || !cancelButton || !preview) return;

            const context = canvas.getContext('2d');
            let image = null;
            let offsetX = 0;
            let offsetY = 0;
            let startX = 0;
            let startY = 0;
            let dragging = false;

            function clampOffsets() {
                if (!image) return;

                const zoom = Number(zoomInput.value || 1);
                const scale = Math.max(canvas.width / image.naturalWidth, canvas.height / image.naturalHeight) * zoom;
                const width = image.naturalWidth * scale;
                const height = image.naturalHeight * scale;
                const maxOffsetX = Math.max(0, (width - canvas.width) / 2);
                const maxOffsetY = Math.max(0, (height - canvas.height) / 2);

                offsetX = Math.min(maxOffsetX, Math.max(-maxOffsetX, offsetX));
                offsetY = Math.min(maxOffsetY, Math.max(-maxOffsetY, offsetY));
            }

            function drawCrop() {
                if (!image) return;

                clampOffsets();
                const zoom = Number(zoomInput.value || 1);
                const scale = Math.max(canvas.width / image.naturalWidth, canvas.height / image.naturalHeight) * zoom;
                const width = image.naturalWidth * scale;
                const height = image.naturalHeight * scale;
                const x = (canvas.width - width) / 2 + offsetX;
                const y = (canvas.height - height) / 2 + offsetY;

                context.clearRect(0, 0, canvas.width, canvas.height);
                context.fillStyle = '#101820';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, x, y, width, height);
            }

            function resetCrop() {
                image = null;
                input.value = '';
                editor.hidden = true;
                offsetX = 0;
                offsetY = 0;
                zoomInput.value = '1';
            }

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = event => {
                    const loadedImage = new Image();
                    loadedImage.onload = () => {
                        image = loadedImage;
                        offsetX = 0;
                        offsetY = 0;
                        zoomInput.value = '1';
                        editor.hidden = false;
                        drawCrop();
                    };
                    loadedImage.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });

            zoomInput.addEventListener('input', drawCrop);

            canvas.addEventListener('pointerdown', event => {
                if (!image) return;
                dragging = true;
                startX = event.clientX - offsetX;
                startY = event.clientY - offsetY;
                canvas.setPointerCapture(event.pointerId);
            });

            canvas.addEventListener('pointermove', event => {
                if (!dragging) return;
                offsetX = event.clientX - startX;
                offsetY = event.clientY - startY;
                clampOffsets();
                drawCrop();
            });

            canvas.addEventListener('pointerup', () => {
                dragging = false;
            });

            applyButton.addEventListener('click', () => {
                if (!image) return;

                canvas.toBlob(blob => {
                    if (!blob) return;

                    const croppedFile = new File([blob], 'profile-cropped.png', { type: 'image/png' });
                    const transfer = new DataTransfer();
                    transfer.items.add(croppedFile);
                    input.files = transfer.files;

                    const previewImage = document.createElement('img');
                    previewImage.src = URL.createObjectURL(blob);
                    previewImage.alt = 'Previsualizacion de la foto de perfil';
                    previewImage.onload = () => URL.revokeObjectURL(previewImage.src);
                    preview.replaceChildren(previewImage);
                    editor.hidden = true;
                }, 'image/png');
            });

            cancelButton.addEventListener('click', resetCrop);
        }

        window.addEventListener('DOMContentLoaded', applySidebarState);
        window.addEventListener('DOMContentLoaded', applyThemeState);
        window.addEventListener('DOMContentLoaded', bindFrameColorLivePreview);
        window.addEventListener('DOMContentLoaded', bindProfileCropEditor);
        window.addEventListener('DOMContentLoaded', bindRecoveryCodesForm);
        window.addEventListener('DOMContentLoaded', bindTwoFactorConfirmForm);
    </script>
</body>
</html>
