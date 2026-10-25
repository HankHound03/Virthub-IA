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
        @if (config('virthub.temas.enabled'))
            @php
                $vhCatalogo = (array) config('virthub.temas.catalogo', []);
            @endphp

            {{--
                Carrusel de temas: en lugar del boton de claro/oscuro. El boton
                muestra el estilo actual y abre un panel con una fila deslizable
                por cada epoca de Windows.
            --}}

            <div class="vh-temas" id="vhTemas">
                <button type="button" class="vh-temas-btn" id="vhTemasBtn"
                        title="Siguiente tema (Alt + flechas)">
                    <span class="vh-temas-dot" id="vhTemasDot" aria-hidden="true"></span>
                    <span id="vhTemasNombre">Tema</span>
                </button>
                <button type="button" class="vh-temas-caret" id="vhTemasAbrir"
                        aria-expanded="false" aria-controls="vhTemasPanel"
                        title="Elegir un esquema concreto">&#9662;</button>


                <div class="vh-temas-panel" id="vhTemasPanel" hidden>
                    @foreach ($vhCatalogo as $era => $datos)
                        <div class="vh-temas-era">
                            <h4>{{ $datos['nombre'] ?? $era }}</h4>

                            <div class="vh-temas-track" data-track="{{ $era }}">
                                @foreach (($datos['variantes'] ?? []) as $variante => $v)
                                    <button type="button" class="vh-tema-tile"
                                            data-era="{{ $era }}"
                                            data-variante="{{ $variante }}"
                                            data-nombre="{{ $datos['nombre'] ?? $era }} · {{ $v['nombre'] ?? $variante }}"
                                            data-imagen="{{ $datos['imagen'] ?? '' }}"
                                            style="--sw-a: {{ $v['a'] ?? '#444' }}; --sw-b: {{ $v['b'] ?? '#888' }};"
                                            title="{{ $datos['nombre'] ?? $era }} - {{ $v['nombre'] ?? $variante }}">
                                        <span class="vh-tema-swatch" aria-hidden="true"></span>
                                        <span class="vh-tema-name">{{ $v['nombre'] ?? $variante }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="vh-temas-nav">
                        <button type="button" data-nav="-1" aria-label="Anterior">&#8249;</button>
                        <button type="button" data-nav="1" aria-label="Siguiente">&#8250;</button>
                        <span style="color:#8fb3d9;font:600 10px 'Segoe UI',sans-serif;">
                            Desliza o usa las flechas
                        </span>
                    </div>
                </div>
            </div>
        @else
            <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <span class="theme-icon" aria-hidden="true"></span>
            </div>
        @endif
        @unless (config('virthub.temas.enabled'))
            <button type="button" class="retro-toggle" onclick="toggleRetroMode()" id="retroToggle" title="Activar tema retro" aria-label="Activar tema retro">Retro</button>
        @endunless
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
        Carrusel de temas historicos de Windows.

        Solo se pinta cuando el entorno lo pide (VIRTHUB_TEMAS=true, que pone
        docker-compose.test.yml). En produccion no aparece nada: alli sigue el
        boton de claro/oscuro de siempre.

        El tema se aplica con dos atributos en el body (data-era y data-variante),
        que es lo que espera public/temas.css. Se guarda en localStorage, y el
        enlace a la hoja lo crea este script en el <head> para no tener que anadir
        la etiqueta a las nueve vistas.
    --}}
    @if (config('virthub.temas.enabled'))
        {{-- Dos capas del wallpaper. Van FUERA del <header> a proposito: la barra
             lleva backdrop-filter, y eso convertiria a cualquier hijo fijo en algo
             relativo a ella, recortando el fondo al tamano de la barra. --}}
        <div class="vh-fondo" id="vhFondoA" aria-hidden="true"></div>
        <div class="vh-fondo" id="vhFondoB" aria-hidden="true"></div>

        <script>
            (function () {
                var CLAVE = 'virthub-tema';
                var POR_DEFECTO = { era: 'vista', variante: 'aero' };

                var boton = document.getElementById('vhTemasBtn');
                var abrir = document.getElementById('vhTemasAbrir');
                var panel = document.getElementById('vhTemasPanel');
                var nombre = document.getElementById('vhTemasNombre');
                var punto = document.getElementById('vhTemasDot');

                if (!boton || !panel) {
                    return;
                }

                var fichas = Array.prototype.slice.call(panel.querySelectorAll('.vh-tema-tile'));

                /* Dos capas del wallpaper: una imagen de fondo no se puede animar,
                   asi que se funde la capa nueva encima de la anterior. */
                var capas = [document.getElementById('vhFondoA'), document.getElementById('vhFondoB')];
                var capaActiva = 0;

                /* Prefiere el WebP (pesa la mitad) y deja el JPG como respaldo. */
                function fondoCss(imagen) {
                    var webp = imagen.replace(/\.jpe?g$/i, '.webp');

                    if (webp === imagen) {
                        return "url('" + imagen + "')";
                    }

                    return 'image-set(url("' + webp + '") type("image/webp"), url("' + imagen + '") type("image/jpeg"))';
                }
                function pintarFondo(imagen) {
                    var actual = capas[capaActiva];

                    if (!actual || !imagen) {
                        return;
                    }

                    if (!actual.style.backgroundImage) {
                        actual.style.backgroundImage = fondoCss(imagen);
                        actual.style.opacity = '1';
                        return;
                    }

                    if (actual.style.backgroundImage.indexOf(imagen) !== -1) {
                        return;
                    }

                    var otra = capas[1 - capaActiva];

                    if (!otra) {
                        return;
                    }

                    otra.style.backgroundImage = fondoCss(imagen);
                    otra.style.opacity = '1';
                    actual.style.opacity = '0';
                    capaActiva = 1 - capaActiva;
                }


                function buscar(era, variante) {
                    return fichas.find(function (f) {
                        return f.dataset.era === era && f.dataset.variante === variante;
                    }) || null;
                }

                function aplicar(era, variante) {
                    var ficha = buscar(era, variante);

                    if (!ficha) {
                        ficha = buscar(POR_DEFECTO.era, POR_DEFECTO.variante);
                        era = POR_DEFECTO.era;
                        variante = POR_DEFECTO.variante;
                    }

                    document.documentElement.dataset.era = era;
                    document.documentElement.dataset.variante = variante;
                    pintarFondo(ficha ? ficha.dataset.imagen : '');

                    if (nombre) {
                        nombre.textContent = ficha ? ficha.dataset.nombre : era;
                    }

                    if (punto && ficha) {
                        var estilo = ficha.getAttribute('style') || '';
                        var a = /--sw-a:\s*([^;]+)/.exec(estilo);
                        var b = /--sw-b:\s*([^;]+)/.exec(estilo);
                        punto.style.background = 'linear-gradient(135deg,' + (a ? a[1].trim() : '#666') + ',' + (b ? b[1].trim() : '#999') + ')';
                    }

                    fichas.forEach(function (f) {
                        f.classList.toggle('activo', f === ficha);
                    });


                    try {
                        localStorage.setItem(CLAVE, JSON.stringify({ era: era, variante: variante }));
                    } catch (e) {
                        /* Sin almacenamiento: el tema dura solo esta pagina. */
                    }

                    /* Deja la ficha elegida a la vista dentro de su fila. */
                    if (ficha && ficha.parentElement) {
                        ficha.parentElement.scrollLeft = Math.max(0, ficha.offsetLeft - 24);
                    }
                }

                fichas.forEach(function (ficha) {
                    ficha.addEventListener('click', function () {
                        aplicar(ficha.dataset.era, ficha.dataset.variante);
                    });
                });

                /* Un clic en el boton grande pasa al siguiente tema; la flechita
                   abre el carrusel para elegir uno concreto. */
                function ciclo(paso) {
                    var actual = document.documentElement.dataset.era + '/' + document.documentElement.dataset.variante;
                    var i = fichas.findIndex(function (f) {
                        return (f.dataset.era + '/' + f.dataset.variante) === actual;
                    });

                    if (i < 0) {
                        i = 0;
                    }

                    var f = fichas[(i + paso + fichas.length) % fichas.length];
                    aplicar(f.dataset.era, f.dataset.variante);
                }

                boton.addEventListener('click', function (evento) {
                    evento.stopPropagation();

                    /* Si el clic viene de terminar un deslizamiento, no se hace nada:
                       el tema ya cambio al soltar. */
                    if (ignorarClic) {
                        ignorarClic = false;
                        return;
                    }

                    ciclo(1);
                });

                /*
                 * Deslizar sobre la pastilla cambia de tema.
                 *
                 * En el telefono es mas natural que apuntar a un boton pequeno: se
                 * arrastra a la izquierda para el siguiente tema y a la derecha para
                 * el anterior. Si el gesto no pasa de 30 px, se trata como un clic.
                 */
                var gestoX = null;
                var gestoY = null;
                var deslizando = false;
                var ignorarClic = false;

                boton.addEventListener('pointerdown', function (evento) {
                    if (evento.pointerType === 'mouse' && evento.button !== 0) {
                        return;
                    }

                    gestoX = evento.clientX;
                    gestoY = evento.clientY;
                    deslizando = false;
                });

                boton.addEventListener('pointermove', function (evento) {
                    if (gestoX === null) {
                        return;
                    }

                    var dx = evento.clientX - gestoX;
                    var dy = evento.clientY - gestoY;

                    if (!deslizando && Math.abs(dx) > 14 && Math.abs(dx) > Math.abs(dy)) {
                        deslizando = true;
                        boton.classList.add('deslizando');
                    }

                    if (deslizando) {
                        /* Se mueve un poco para dar la sensacion de arrastre. */
                        boton.style.transform = 'translateX(' + Math.max(-22, Math.min(22, dx)) + 'px)';
                    }
                });

                function terminarGesto(evento) {
                    if (gestoX === null) {
                        return;
                    }

                    var dx = evento.clientX - gestoX;
                    gestoX = null;
                    gestoY = null;
                    boton.classList.remove('deslizando');
                    boton.style.transform = '';

                    if (!deslizando) {
                        return;
                    }

                    deslizando = false;

                    if (Math.abs(dx) < 30) {
                        return;
                    }

                    /* Evita que el navegador cuente tambien el clic. */
                    ignorarClic = true;
                    setTimeout(function () {
                        ignorarClic = false;
                    }, 400);

                    ciclo(dx < 0 ? 1 : -1);
                }

                boton.addEventListener('pointerup', terminarGesto);
                boton.addEventListener('pointercancel', function () {
                    gestoX = null;
                    gestoY = null;
                    deslizando = false;
                    boton.classList.remove('deslizando');
                    boton.style.transform = '';
                });

                if (abrir) {
                    abrir.addEventListener('click', function (evento) {
                        evento.stopPropagation();
                        var abierto = panel.hidden;
                        panel.hidden = !abierto;
                        abrir.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                    });
                }

                /* Alt + flechas: cambiar sin soltar el teclado. */
                document.addEventListener('keydown', function (evento) {
                    if (!evento.altKey) {
                        return;
                    }

                    if (evento.key === 'ArrowRight') {
                        evento.preventDefault();
                        ciclo(1);
                    } else if (evento.key === 'ArrowLeft') {
                        evento.preventDefault();
                        ciclo(-1);
                    }
                });

                panel.addEventListener('click', function (evento) {
                    evento.stopPropagation();
                });

                document.addEventListener('click', function () {
                    if (!panel.hidden) {
                        panel.hidden = true;
                        if (abrir) { abrir.setAttribute('aria-expanded', 'false'); }
                    }
                });

                document.addEventListener('keydown', function (evento) {
                    if (evento.key === 'Escape' && !panel.hidden) {
                        panel.hidden = true;
                        if (abrir) { abrir.setAttribute('aria-expanded', 'false'); }
                    }
                });

                /* Flechas: mueven la fila de la epoca que se esta mirando. */
                panel.querySelectorAll('[data-nav]').forEach(function (flecha) {
                    flecha.addEventListener('click', function () {
                        var paso = parseInt(flecha.dataset.nav, 10) * 200;
                        var filas = panel.querySelectorAll('.vh-temas-track');

                        filas.forEach(function (fila) {
                            fila.scrollLeft += paso;
                        });
                    });
                });

                /* La hoja de estilos y el tema ya los puso el arranque del <head>
                   antes de pintar. Aqui solo se sincroniza el boton y el fondo. */
                var guardado = null;

                try {
                    guardado = JSON.parse(localStorage.getItem(CLAVE) || 'null');
                } catch (e) {
                    guardado = null;
                }

                aplicar(
                    guardado && guardado.era ? guardado.era : POR_DEFECTO.era,
                    guardado && guardado.variante ? guardado.variante : POR_DEFECTO.variante
                );
            })();
        </script>
    @endif
