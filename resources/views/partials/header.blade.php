<header>
    <h1 class="brand-mark">
        <a href="{{ url('/') }}" aria-label="Ir al inicio de VirtHub">
            <img src="{{ asset('pics/logovh.png') }}" alt="VirtHub" class="brand-logo">
        </a>
    </h1>
    @include('partials.navigation-menu', ['currentUser' => $currentUser ?? null, 'currentPage' => $currentPage ?? 'home'])
    <div class="header-controls">
        @if (!empty($currentUser) && ($currentUser['role'] ?? 'guest') !== 'guest')
            <form method="GET" action="{{ url('/buscar-amigos') }}" class="header-friend-search" role="search">
                <input type="search" name="q" placeholder="Buscar amigos" aria-label="Buscar amigos">
                <button type="submit" title="Buscar amigos" aria-label="Buscar amigos">⌕</button>
            </form>
        @endif
        <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
            <span class="theme-icon" aria-hidden="true"></span>
        </div>
        <button type="button" class="retro-toggle" onclick="toggleRetroMode()" id="retroToggle" title="Activar tema retro" aria-label="Activar tema retro">Retro</button>
        @if (!empty($currentUser))
            @php
                $profileImage = (string) ($currentUser['profile_image_path'] ?? '');
                $frameColor = (string) ($currentUser['profile_frame_color'] ?? '#6ea8ff');
                $userInitial = strtoupper(substr((string) ($currentUser['username'] ?? 'U'), 0, 1));
            @endphp
            <div class="header-profile-dock toggleable-profile-menu" onclick="toggleProfileMenu(event)" title="Menu de perfil" aria-label="Menu de perfil">
                <div class="profile-aero-frame profile-aero-frame-sm" style="--profile-frame-color: {{ $frameColor }};">
                    {{-- Si el archivo del avatar ya no existe se muestra la inicial,
                         no un icono de imagen rota. --}}
                    @if ($profileImage !== '' && \App\Support\AttachmentStorage::exists($profileImage))
                        <img src="{{ \App\Support\AttachmentStorage::url($profileImage) }}" alt="Foto de perfil de {{ $currentUser['username'] }}" loading="lazy">
                    @else
                        <span>{{ $userInitial }}</span>
                    @endif
                </div>
                <div class="profile-menu" onclick="event.stopPropagation()">
                    @if (($currentUser['role'] ?? 'guest') !== 'guest')
                        <button type="button" onclick="location.href='{{ url('/perfil/' . rawurlencode((string) $currentUser['username'])) }}'">Mi Perfil</button>
                        <button type="button" onclick="location.href='{{ url('/configuracion') }}'">Configuracion</button>
                    @endif
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit">Cerrar Sesion</button>
                    </form>
                </div>
            </div>
                @else
                    <button type="button" class="header-login-button" id="openLoginModal">Iniciar sesión</button>
        @endif
    </div>
</header>

{{--
    Errores de validacion en cualquier pagina, para usuarios con sesion.

    Antes solo se pintaban dentro del modal de inicio de sesion, que solo existe
    para invitados: un usuario registrado enviaba un formulario no valido (un
    username repetido, un archivo con extension no permitida, uno rechazado por el
    antivirus...) y no veia absolutamente nada.

    Los invitados no lo necesitan: ya lo ven dentro de su modal.
--}}
@if (!empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest') && isset($errors) && $errors->any())
    <div class="page-errors" role="alert">
        @foreach ($errors->all() as $error)
            <p class="auth-message auth-error">{{ $error }}</p>
        @endforeach
    </div>
@endif

        @if (empty($currentUser) || (($currentUser['role'] ?? 'guest') === 'guest'))
            <div class="header-login-modal" id="headerLoginModal" aria-hidden="true">
                <div class="header-login-card" role="dialog" aria-modal="true" aria-labelledby="headerLoginTitle" onclick="event.stopPropagation()">
                    <button type="button" class="header-login-close" id="closeLoginModal" aria-label="Cerrar">×</button>
                    <h2 id="headerLoginTitle">Iniciar sesión</h2>
                    @if (session('error'))
                        <p class="auth-message auth-error" role="alert">{{ session('error') }}</p>
                    @endif
                    {{-- $errors lo inyecta ShareErrorsFromSession; se comprueba por si
                         la vista se renderiza sin ese middleware. --}}
                    @if (isset($errors) && $errors->any())
                        <p class="auth-message auth-error" role="alert">{{ $errors->first() }}</p>
                    @endif
                    @if (session('two_factor_pending_username'))
                        <p>Introduce el codigo de Google Authenticator para continuar.</p>
                        <form method="POST" action="{{ url('/login/2fa') }}">
                            @csrf
                            <label for="headerTwoFactorCode">Codigo de seis digitos</label>
                            <input type="text" id="headerTwoFactorCode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                            <button type="submit">Verificar codigo</button>
                        </form>
                        <form method="POST" action="{{ url('/login/2fa/recovery') }}">
                            @csrf
                            <label for="headerRecoveryCode">Codigo de recuperacion</label>
                            <input type="text" id="headerRecoveryCode" name="recovery_code" maxlength="32" required>
                            <button type="submit">Usar recuperacion</button>
                        </form>
                    @else
                        <form method="POST" action="{{ url('/login') }}">
                            @csrf
                            <label for="headerLoginUsername">Username</label>
                            <input type="text" id="headerLoginUsername" name="username" value="{{ old('username') }}" required autofocus>
                            <label for="headerLoginPassword">Contraseña</label>
                            <input type="password" id="headerLoginPassword" name="password" required>
                            <button type="submit">Entrar</button>
                        </form>
                        <form method="POST" action="{{ url('/guest-login') }}">
                            @csrf
                            <button type="submit" class="header-guest-button">Entrar como invitado</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

<script>
    (() => {
        const retroStorageKey = 'virthub_retro_mode';
        const secretSequence = 'win95';
        let typedSequence = '';

        function applyRetroMode(enabled) {
            document.body.classList.toggle('retro-mode', enabled);
            localStorage.setItem(retroStorageKey, enabled ? '1' : '0');
        }

        window.toggleRetroMode = () => {
            applyRetroMode(!document.body.classList.contains('retro-mode'));
        };

        document.addEventListener('DOMContentLoaded', () => {
            applyRetroMode(localStorage.getItem(retroStorageKey) === '1');
        });

        document.addEventListener('keydown', event => {
            if (event.target.matches('input, textarea, select, [contenteditable="true"]')) {
                return;
            }

            typedSequence = (typedSequence + event.key.toLowerCase()).slice(-secretSequence.length);
            if (typedSequence === secretSequence) {
                window.toggleRetroMode();
                typedSequence = '';
            }
        });
    })();

    (() => {
        const modal = document.getElementById('headerLoginModal');
        const openButton = document.getElementById('openLoginModal');
        const closeButton = document.getElementById('closeLoginModal');
        if (!modal || !openButton) return;
        const shouldOpen = @json((bool) (session('two_factor_pending_username') || session('error') || (isset($errors) && $errors->any())));
        const card = modal.querySelector('.header-login-card');

        const getErrorMessage = payload => {
            if (payload.error) return payload.error;
            if (payload.message) return payload.message;
            const firstError = payload.errors ? Object.values(payload.errors)[0] : null;
            return Array.isArray(firstError) ? firstError[0] : 'No se pudo completar la solicitud.';
        };

        const showFeedback = message => {
            let feedback = card.querySelector('[data-login-feedback]');
            if (!feedback) {
                feedback = document.createElement('p');
                feedback.dataset.loginFeedback = 'true';
                feedback.className = 'auth-message auth-error';
                feedback.setAttribute('role', 'alert');
                card.querySelector('h2')?.after(feedback);
            }
            feedback.textContent = message;
            feedback.hidden = false;
        };

        const submitJsonForm = async form => {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || ''
                },
                body: new FormData(form)
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(getErrorMessage(payload));
            return payload;
        };

        const bindAjaxForm = form => {
            if (!form || form.dataset.ajaxBound === 'true') return;
            form.dataset.ajaxBound = 'true';
            form.addEventListener('submit', async event => {
                event.preventDefault();
                try {
                    const payload = await submitJsonForm(form);
                    if (payload.two_factor_required) {
                        showTwoFactorStep();
                        return;
                    }
                    if (payload.authenticated) window.location.reload();
                } catch (error) {
                    showFeedback(error.message);
                }
            });
        };

        const showTwoFactorStep = () => {
            card.querySelectorAll('form').forEach(form => {
                form.hidden = true;
            });

            if (card.querySelector('[data-two-factor-step]')) return;

            const token = card.querySelector('input[name="_token"]')?.value || '';
            const step = document.createElement('div');
            step.dataset.twoFactorStep = 'true';
            step.innerHTML = `
                <p>Introduce el codigo de Google Authenticator para continuar.</p>
                <form method="POST" action="{{ url('/login/2fa') }}">
                    <input type="hidden" name="_token" value="${token}">
                    <label for="dynamicTwoFactorCode">Codigo de seis digitos</label>
                    <input type="text" id="dynamicTwoFactorCode" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                    <button type="submit">Verificar codigo</button>
                </form>
                <form method="POST" action="{{ url('/login/2fa/recovery') }}">
                    <input type="hidden" name="_token" value="${token}">
                    <label for="dynamicRecoveryCode">Codigo de recuperacion</label>
                    <input type="text" id="dynamicRecoveryCode" name="recovery_code" maxlength="32" required>
                    <button type="submit">Usar recuperacion</button>
                </form>`;
            card.appendChild(step);
            step.querySelectorAll('form').forEach(bindAjaxForm);
            step.querySelector('input[name="code"]')?.focus();
        };

        const close = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        };

        openButton.addEventListener('click', () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            (document.getElementById('headerLoginUsername') || document.getElementById('headerTwoFactorCode'))?.focus();
        });
        closeButton?.addEventListener('click', close);
        modal.addEventListener('click', close);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') close();
        });

        card.querySelectorAll('form[action$="/login"], form[action$="/login/2fa"], form[action$="/login/2fa/recovery"]')
            .forEach(bindAjaxForm);

        if (shouldOpen) {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            (document.getElementById('headerLoginUsername') || document.getElementById('headerTwoFactorCode'))?.focus();
        }
    })();

    (() => {
        const prefetchedUrls = new Set();

        const canPrefetch = link => {
            if (!link || link.dataset.noPrefetch !== undefined || link.target === '_blank') return false;
            if (link.closest('form') || link.hasAttribute('download')) return false;

            const url = new URL(link.href, window.location.href);
            return url.origin === window.location.origin
                && ['http:', 'https:'].includes(url.protocol)
                && url.pathname !== '/contenedor/launch';
        };

        const prefetch = link => {
            if (!canPrefetch(link)) return;

            const url = new URL(link.href, window.location.href).href;
            if (prefetchedUrls.has(url) || url === window.location.href) return;

            prefetchedUrls.add(url);
            const hint = document.createElement('link');
            hint.rel = 'prefetch';
            hint.href = url;
            hint.as = 'document';
            document.head.appendChild(hint);
        };

        document.addEventListener('pointerover', event => {
            const link = event.target.closest?.('a[href]');
            if (!link) return;

            if (window.requestIdleCallback) {
                window.requestIdleCallback(() => prefetch(link), { timeout: 350 });
            } else {
                window.setTimeout(() => prefetch(link), 80);
            }
        }, { passive: true });
    })();
</script>

    {{--
        Llevar la vista hasta el aviso.

        Los mensajes de error se pintan arriba, pero tras enviar un formulario el
        navegador suele devolver al usuario a la altura donde estaba: el aviso
        existia y no se veia. Por ejemplo, al subir un archivo rechazado por el
        antivirus, el mensaje quedaba fuera de pantalla.
    --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const aviso = document.getElementById('avisoPublicar') || document.querySelector('.page-errors .auth-error, .forum-shell .auth-error');

            if (aviso) {
                aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>

    {{--
        Selector de temas historicos de Windows (XP, Vista, 7, 8, 10).

        Solo se pinta cuando el entorno lo pide (VIRTHUB_TEMAS=true, que pone
        docker-compose.test.yml). En produccion no aparece nada.

        El enlace al CSS lo crea este mismo script en el <head>, para no tener que
        anadir la etiqueta a las nueve vistas. El tema guardado se aplica cuando la
        hoja termina de cargar, asi no se ve un parpadeo con el estilo anterior.
    --}}
    @if (config('virthub.temas.enabled'))
        <div class="vh-temas" id="vhTemas" role="group" aria-label="Tema de diseno">
            <button type="button" class="vh-temas-boton" data-tema="">Original</button>
            <button type="button" class="vh-temas-boton" data-tema="xp">XP</button>
            <button type="button" class="vh-temas-boton" data-tema="vista">Vista</button>
            <button type="button" class="vh-temas-boton" data-tema="win7">7</button>
            <button type="button" class="vh-temas-boton" data-tema="win8">8</button>
            <button type="button" class="vh-temas-boton" data-tema="win10">10</button>
        </div>

        <script>
            (function () {
                var CLAVE = 'virthub-tema';
                var CLASES = ['tema-xp', 'tema-vista', 'tema-win7', 'tema-win8', 'tema-win10'];

                function aplicar(tema) {
                    CLASES.forEach(function (clase) {
                        document.body.classList.remove(clase);
                    });

                    if (tema) {
                        document.body.classList.add('tema-' + tema);
                    }

                    document.querySelectorAll('.vh-temas-boton').forEach(function (boton) {
                        boton.classList.toggle('activo', (boton.dataset.tema || '') === (tema || ''));
                    });

                    try {
                        localStorage.setItem(CLAVE, tema || '');
                    } catch (e) {
                        /* Sin almacenamiento: el tema dura solo esta pagina. */
                    }
                }

                document.querySelectorAll('.vh-temas-boton').forEach(function (boton) {
                    boton.addEventListener('click', function () {
                        aplicar(boton.dataset.tema);
                    });
                });

                var enlace = document.createElement('link');
                enlace.rel = 'stylesheet';
                enlace.href = @json(asset('temas.css'));
                enlace.onload = function () {
                    var guardado = '';

                    try {
                        guardado = localStorage.getItem(CLAVE) || '';
                    } catch (e) {
                        guardado = '';
                    }

                    aplicar(guardado);
                };

                document.head.appendChild(enlace);
            })();
        </script>
    @endif
