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
        /* =============================================================
           DOS COLUMNAS: EXPLICACION A LA IZQUIERDA, FORMULARIO A LA DERECHA
           =============================================================
           Antes cada panel era una columna estrecha pegada a la izquierda y el
           resto de la pantalla quedaba vacio.
           ------------------------------------------------------------- */

        .config-dos-columnas {
            display: grid;
            grid-template-columns: minmax(220px, 300px) minmax(0, 1fr);
            gap: 30px;
            align-items: start;
        }

        .config-lateral {
            position: sticky;
            top: 16px;
        }

        .config-lateral .config-titulo {
            margin-bottom: 8px;
        }

        .config-lateral-form {
            min-width: 0;
            padding: 20px;
            border: 1px solid var(--vh-border);
            border-radius: 10px;
            background: color-mix(in srgb, var(--vh-surface) 85%, transparent);
        }

        .config-lista {
            margin: 14px 0 0;
            padding-left: 18px;
            color: var(--vh-text-soft);
            font-size: 11.5px;
            line-height: 1.6;
        }

        .config-lista li {
            margin-bottom: 5px;
        }

        .config-lista li::marker {
            color: var(--vh-evento, #e0a63c);
        }

        @media (max-width: 860px) {
            .config-dos-columnas {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .config-lateral {
                position: static;
            }

            .config-lateral-form {
                padding: 14px;
            }
        }
    </style>
    @include('partials.tema-boot')
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

        {{--
            Tres apartados en pestanas.

            Antes eran dos columnas con todo apilado: marco, foto, recorte,
            contrasena y las dos ramas de la verificacion en dos pasos. Se veia
            amontonado y costaba encontrar nada.

            Ahora cada cosa tiene su sitio y una sola se ve a la vez. La pestana de
            inicio depende de lo que necesite atencion: si la verificacion esta
            configurandose, abre en "Dos pasos"; si no, en "Perfil".
        --}}
        <div class="config-tabs" role="tablist" aria-label="Secciones de configuración">
            <button type="button" class="config-tab is-active" role="tab" aria-selected="true"
                    aria-controls="panelPerfil" data-config-tab="perfil">
                <span class="config-tab-icono" aria-hidden="true"><svg viewBox="0 0 16 16" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.6"/><path d="M3 13.4c.6-2.4 2.6-3.8 5-3.8s4.4 1.4 5 3.8"/></svg></span>  Perfil
            </button>
            <button type="button" class="config-tab" role="tab" aria-selected="false"
                    aria-controls="panelContrasena" data-config-tab="contrasena">
                <span class="config-tab-icono" aria-hidden="true"><svg viewBox="0 0 16 16" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="5.6" cy="10.4" r="2.9"/><path d="M7.7 8.3 13 3M10.8 5.2l1.7 1.7M12.2 3.8l1.6 1.6"/></svg></span>  Contraseña
            </button>
            <button type="button" class="config-tab" role="tab" aria-selected="false"
                    aria-controls="panelDosPasos" data-config-tab="dos-pasos">
                <span class="config-tab-icono" aria-hidden="true"><svg viewBox="0 0 16 16" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 1.8 13 3.6v4.2c0 3.2-2.1 5.6-5 6.4-2.9-.8-5-3.2-5-6.4V3.6z"/><path d="M6 8l1.6 1.6L10.4 6.6"/></svg></span>  Dos pasos
                @if ($twoFactorEnabled)
                    <span class="config-tab-punto" title="Activo" aria-label="Activo"></span>
                @endif
            </button>
        </div>

        {{-- ------------------------------- PERFIL ------------------------------- --}}
        <section class="config-panel is-active" id="panelPerfil" data-config-panel="perfil" role="tabpanel">
            <div class="config-split">
                <aside class="config-vista">
                    <div class="profile-aero-frame profile-aero-frame-lg" id="profileFramePreview" style="--profile-frame-color: {{ $frameColor }};">
                        {{-- La URL se genera por el enlace public/storage y solo se
                             pinta si el archivo existe; si no, se muestra la inicial. --}}
                        @if ($profileImage !== '' && \App\Support\AttachmentStorage::exists($profileImage))
                            <img src="{{ \App\Support\AttachmentStorage::url($profileImage) }}" alt="Foto de perfil de {{ $currentUser['username'] }}" loading="lazy">
                        @else
                            <span>{{ $userInitial }}</span>
                        @endif
                    </div>
                    <strong class="config-vista-nombre">{{ $currentUser['username'] }}</strong>
                    <small class="config-vista-nota">Así te ven en el foro y en el chat.</small>
                </aside>

                <form method="POST" action="/profile/appearance" enctype="multipart/form-data" class="config-form">
                    @csrf

                    <div class="config-campo">
                        <label for="frameColorInput">Color del marco</label>
                        <p class="config-ayuda">El anillo que rodea tu foto. Elige uno que te guste: aparece en todo el sitio.</p>
                        <input type="color" name="frame_color" id="frameColorInput" value="{{ $frameColor }}" aria-label="Color del marco de perfil">
                    </div>

                    <div class="config-campo">
                        <label for="profileImageInput">Foto de perfil</label>
                        <p class="config-ayuda">Opcional. Se recorta en cuadrado; puedes ajustar el zoom antes de guardar.</p>
                        <input type="file" name="profile_image" id="profileImageInput" accept="image/png,image/jpeg,image/webp,image/gif">

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
                    </div>

                    <div class="config-acciones">
                        <button type="submit" class="config-boton">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </section>

        {{-- ----------------------------- CONTRASENA ----------------------------- --}}
        <section class="config-panel" id="panelContrasena" data-config-panel="contrasena" role="tabpanel" hidden>
            <div class="config-dos-columnas">
                <aside class="config-lateral">
                    <h3 class="config-titulo">Cambiar contraseña</h3>
                    <p class="config-ayuda">
                        Escribe tu contraseña actual y la nueva dos veces. La actual se pide para
                        confirmar que eres tú.
                    </p>

                    <ul class="config-lista">
                        <li>Al menos seis caracteres.</li>
                        <li>La nueva debe ser distinta de la actual.</li>
                        <li>Si no la recuerdas, pídele al administrador que te la restablezca.</li>
                    </ul>
                </aside>

                <div class="config-lateral-form">
                <form method="POST" action="/profile/password" class="config-form">
                    @csrf

                    <div class="config-campo">
                        <label for="currentPassword">Contraseña actual</label>
                        <input type="password" id="currentPassword" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="config-campo">
                        <label for="newPassword">Contraseña nueva</label>
                        <input type="password" id="newPassword" name="new_password" required autocomplete="new-password">
                    </div>

                    <div class="config-campo">
                        <label for="newPasswordConfirm">Repite la contraseña nueva</label>
                        <input type="password" id="newPasswordConfirm" name="new_password_confirmation" required autocomplete="new-password">
                    </div>

                    <div class="config-acciones">
                        <button type="submit" class="config-boton">Cambiar mi contraseña</button>
                    </div>
                </form>
                </div>
            </div>
        </section>

        {{-- ----------------------------- DOS PASOS ----------------------------- --}}
        <section class="config-panel" id="panelDosPasos" data-config-panel="dos-pasos" role="tabpanel" hidden>
            <div class="config-dos-columnas">
                <aside class="config-lateral">
                    <h3 class="config-titulo">Verificación en dos pasos</h3>

                    <div class="config-estado {{ $twoFactorEnabled ? 'is-activo' : 'is-inactivo' }}">
                    <span class="config-estado-bola" aria-hidden="true"></span>
                    <div>
                        <strong>{{ $twoFactorEnabled ? 'Verificación en dos pasos activa' : 'Verificación en dos pasos desactivada' }}</strong>
                        <small>
                            {{ $twoFactorEnabled
                                ? 'Al iniciar sesión se pide el código de seis dígitos de tu aplicación de autenticación.'
                                : 'Ahora mismo solo hace falta tu contraseña para entrar.' }}
                        </small>
                    </div>
                    </div>

                    <p class="config-ayuda">
                        @if ($twoFactorEnabled)
                            Todo está en orden. A la derecha quedan las dos operaciones que se hacen de vez en cuando.
                        @else
                            Sin ella, tu contraseña sola basta para entrar. Activarla toma un minuto y un teléfono. Recomendadas: Google Authenticator y Microsoft Authenticator.
                        @endif
                    </p>
                </aside>

                <div class="config-lateral-form">

                @if (!empty($twoFactorRecoveryCodes = session('two_factor_recovery_codes')))
                    <div class="config-aviso">
                        <strong>Guarda estos códigos ahora</strong>
                        <p>Cada uno sirve una sola vez, por si pierdes el teléfono. No se volverán a mostrar.</p>
                        <pre class="two-factor-recovery">{{ implode("\n", $twoFactorRecoveryCodes) }}</pre>
                    </div>
                @endif

                @if ($twoFactorEnabled)
                    <details class="config-acordeon">
                        <summary>Generar códigos de recuperación nuevos</summary>
                        <form method="POST" action="/security/2fa/recovery-codes" class="config-form" id="recoveryCodesForm">
                            @csrf
                            <p class="config-ayuda">Se piden tu contraseña y el código actual para confirmar que eres tú.</p>

                            <div class="config-campo">
                                <label for="recoveryCurrentPassword">Contraseña actual</label>
                                <input type="password" id="recoveryCurrentPassword" name="current_password" required autocomplete="current-password">
                            </div>

                            <div class="config-campo">
                                <label for="recoveryCurrentCode">Código de Authenticator</label>
                                <input type="text" id="recoveryCurrentCode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                            </div>

                            <div class="config-acciones">
                                <button type="submit" class="config-boton">Generar códigos</button>
                            </div>

                            <p class="auth-message" id="recoveryCodesFeedback" hidden role="alert"></p>
                            <pre class="two-factor-recovery" id="recoveryCodesOutput" hidden></pre>
                        </form>
                    </details>

                    <details class="config-acordeon config-acordeon-peligro">
                        <summary>Desactivar la verificación en dos pasos</summary>
                        <form method="POST" action="/security/2fa/disable" class="config-form">
                            @csrf
                            <p class="config-ayuda">A partir de ese momento solo se pedirá la contraseña. Puedes volver a activarla cuando quieras.</p>

                            <div class="config-campo">
                                <label for="disableCurrentPassword">Contraseña actual</label>
                                <input type="password" id="disableCurrentPassword" name="current_password" required autocomplete="current-password">
                            </div>

                            <div class="config-campo">
                                <label for="disableCurrentCode">Código de Authenticator</label>
                                <input type="text" id="disableCurrentCode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                            </div>

                            <div class="config-acciones">
                                <button type="submit" class="config-boton config-boton-peligro">Desactivar</button>
                            </div>
                        </form>
                    </details>
                @elseif ($twoFactorSetupQr)
                    <p class="config-ayuda config-ayuda-ancha">
                        Escanea el código con tu aplicación de autenticación y escribe el número que aparezca
                        para terminar de activarla.
                    </p>

                    <div class="config-qr">
                        <img class="two-factor-qr" src="{{ $twoFactorSetupQr }}" alt="Código QR para configurar la verificación en dos pasos" width="240" height="240">

                        <div class="twofa-recomendadas">
                            <strong>Recomendadas</strong>
                            <p>Google Authenticator y Microsoft Authenticator. También funcionan Authy, Aegis, FreeOTP, 1Password, Bitwarden o KeePassXC: el código es estándar y cualquiera de ellas sirve.</p>
                        </div>
                        <form method="POST" action="/security/2fa/confirm" class="config-form" id="twoFactorConfirmForm">
                            @csrf

                            <div class="config-campo">
                                <label for="confirmCode">Código de seis dígitos</label>
                                <input type="text" id="confirmCode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                            </div>

                            <div class="config-acciones">
                                <button type="submit" class="config-boton">Confirmar y activar</button>
                            </div>
                        </form>
                    </div>

                    <p class="auth-message" id="activationRecoveryFeedback" hidden role="alert"></p>
                    <pre class="two-factor-recovery" id="activationRecoveryCodes" hidden></pre>
                @else
                    <p class="config-ayuda config-ayuda-ancha">
                        Con la verificación en dos pasos, tu contraseña sola no basta: hace falta
                        también un código que cambia cada treinta segundos en tu teléfono.
                    </p>

                    <form method="POST" action="/security/2fa/setup" class="config-form">
                        @csrf
                        <div class="config-acciones">
                            <button type="submit" class="config-boton">Configurar aplicación de autenticación</button>
                        </div>
                    </form>
                @endif
                </div>
            </div>
        </section>

        <script>
            (function () {
                /*
                 * Pestanas de la configuracion.
                 *
                 * Solo se oculta lo que no toca: si el JavaScript falla, la pagina
                 * sigue sirviendo, porque todas las secciones estan en el documento y
                 * los formularios conservan su accion.
                 */
                const tabs = Array.from(document.querySelectorAll('[data-config-tab]'));
                const paneles = Array.from(document.querySelectorAll('[data-config-panel]'));

                function abrir(nombre) {
                    tabs.forEach(tab => {
                        const activo = tab.dataset.configTab === nombre;
                        tab.classList.toggle('is-active', activo);
                        tab.setAttribute('aria-selected', activo ? 'true' : 'false');
                    });

                    paneles.forEach(panel => {
                        const activo = panel.dataset.configPanel === nombre;
                        panel.classList.toggle('is-active', activo);
                        panel.hidden = !activo;
                    });
                }

                tabs.forEach(tab => {
                    tab.addEventListener('click', () => abrir(tab.dataset.configTab));

                    tab.addEventListener('keydown', (evento) => {
                        /*
                         * Alt + flechas cambia de tema: ese atajo es suyo y nada mas.
                         * Sin esta salida, pulsar Alt y una flecha cambiaba el tema Y
                         * la pestaña a la vez.
                         */
                        if (evento.altKey) return;

                        if (evento.key !== 'ArrowRight' && evento.key !== 'ArrowLeft') return;
                        evento.preventDefault();

                        const indice = tabs.indexOf(tab);
                        const siguiente = evento.key === 'ArrowRight'
                            ? (indice + 1) % tabs.length
                            : (indice - 1 + tabs.length) % tabs.length;

                        tabs[siguiente].focus();
                        abrir(tabs[siguiente].dataset.configTab);
                    });
                });

                /*
                 * Pestaña de entrada:
                 *   1. la que pida el servidor tras una accion (cambiar la
                 *      contrasena deja en "Contrasena", cambiar la foto en
                 *      "Perfil"), para no devolver a la persona al inicio;
                 *   2. si algo pide atencion en "Dos pasos", esa.
                 */
                @if (session('config_tab'))
                    abrir(@json(session('config_tab')));
                @elseif ($twoFactorSetupQr || session('two_factor_recovery_codes') || session('error'))
                    abrir('dos-pasos');
                @endif
            })();
        </script>

    </div>

    <footer>{{ config('virthub.version') }}</footer>

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
