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
                    @if ($profileImage !== '')
                        <img src="{{ asset($profileImage) }}" alt="Foto de perfil de {{ $currentUser['username'] }}" loading="lazy">
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

        @if (empty($currentUser) || (($currentUser['role'] ?? 'guest') === 'guest'))
            <div class="header-login-modal" id="headerLoginModal" aria-hidden="true">
                <div class="header-login-card" role="dialog" aria-modal="true" aria-labelledby="headerLoginTitle" onclick="event.stopPropagation()">
                    <button type="button" class="header-login-close" id="closeLoginModal" aria-label="Cerrar">×</button>
                    <h2 id="headerLoginTitle">Iniciar sesión</h2>
                    @if (session('error'))
                        <p class="auth-message auth-error" role="alert">{{ session('error') }}</p>
                    @endif
                    @if ($errors->any())
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
        const shouldOpen = @json((bool) (session('two_factor_pending_username') || session('error') || $errors->any()));
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