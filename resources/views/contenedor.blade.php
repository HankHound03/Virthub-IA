<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bienvenido {{ $currentUser['username'] ?? 'Usuario' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    @include('partials.tema-boot')
</head>
<body>
    <audio id="chatNotificationAudio" preload="auto" style="display: none;">
        <source src="{{ asset('sounds/chat-notificacion.mp3') }}" type="audio/mpeg">
    </audio>

    @include('partials.header', [
        'pageTitle' => (($currentUser['role'] ?? 'guest') === 'guest') ? 'Bienvenido Invitado' : 'Bienvenido ' . ($currentUser['username'] ?? 'Usuario'),
        'currentUser' => $currentUser ?? null,
        'currentPage' => 'contenedor'
    ])


    <div class="container-wrapper">
        <div class="chat-panel" id="chatPanel">
            <div class="chat-header">
                <h3>Chat</h3>
                <button type="button" class="chat-close" onclick="toggleChat()">×</button>
            </div>
            
            <div class="chat-tabs" id="chatTabs">
                @if (($currentUser['role'] ?? 'guest') !== 'guest')
                <button type="button" class="chat-tab-btn active" onclick="switchChatTab('messages')" data-tab="messages">Mensajes</button>
                <button type="button" class="chat-tab-btn" onclick="switchChatTab('users')" data-tab="users">Usuarios</button>
                <button type="button" class="chat-tab-btn" onclick="switchChatTab('friendRequests')" data-tab="friendRequests">Solicitudes</button>
                @if (($currentUser['role'] ?? 'user') === 'admin')
                <button type="button" class="chat-tab-btn" onclick="openOllamaChat()" data-tab="ollama">IA</button>
                @endif
                <button type="button" class="chat-tab-btn" onclick="switchChatTab('broadcast')" data-tab="broadcast">Anuncios</button>
                @else
                <button type="button" class="chat-tab-btn active" onclick="switchChatTab('broadcast')" data-tab="broadcast">Anuncios</button>
                @endif
            </div>

            @if (($currentUser['role'] ?? 'guest') !== 'guest')
            <div id="messagesView" class="chat-view active">
                <div class="chat-messages" id="chatMessages">
                    <p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Sin mensajes aún. Selecciona un usuario.</p>
                </div>
                <div class="chat-input-area">
                    <input type="text" id="chatInput" placeholder="Escribe un mensaje..." onkeypress="if(event.key==='Enter') sendChatMessage();">
                    <button type="button" onclick="sendChatMessage()" class="chat-send-btn">Enviar</button>
                </div>
            </div>

            <div id="usersView" class="chat-view">
                <div class="chat-users-list" id="chatUsersList">
                    <p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando usuarios...</p>
                </div>
            </div>

            <div id="friendRequestsView" class="chat-view">
                <div class="chat-messages" id="chatFriendRequestsList">
                    <p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando solicitudes...</p>
                </div>
            </div>

            @if (($currentUser['role'] ?? 'user') === 'admin')
            <div id="ollamaView" class="chat-view">
                <div class="chat-messages" id="ollamaMessages">
                    <p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando chat de IA...</p>
                </div>
                {{--
                    Monitor de recursos.
                    Muestra la GPU mientras se conversa, para ver cuanto esta
                    trabajando el equipo. Solo aparece si el monitor esta activo
                    (entorno de pruebas por ahora).
                --}}
                @if (config('virthub.ia.monitor'))
                    <div class="ia-monitor" id="iaMonitor" hidden>
                        <span class="ia-monitor-chip"><span>GPU</span><b id="iaMonGpu">—</b></span>
                        <span class="ia-monitor-chip"><span>VRAM</span><b id="iaMonVram">—</b></span>
                        <span class="ia-monitor-chip"><span>Temp</span><b id="iaMonTemp">—</b></span>
                        <span class="ia-monitor-chip"><span>Modelo</span><b id="iaMonModelo">—</b></span>
                        <span class="ia-monitor-chip" id="iaMonVelocidadChip" hidden><span>Velocidad</span><b id="iaMonVelocidad">—</b></span>
                    </div>
                @endif

                <div class="ia-acciones">
                    <button type="button" class="ia-borrar" id="iaBorrarHistorial"
                            onclick="confirmarBorradoIA()">
                        Borrar historial
                    </button>

                    <span class="ia-confirmar" id="iaConfirmar" hidden>
                        <span>¿Borrar el historial?</span>
                        <button type="button" class="ia-si" onclick="borrarHistorialIA()">Sí, borrar</button>
                        <button type="button" class="ia-no" onclick="cancelarBorradoIA()">Cancelar</button>
                    </span>
                </div>

                {{-- Mensajes del panel: aquí se ve el resultado, sin ventanas del navegador. --}}
                <p class="ia-aviso" id="iaAviso" hidden role="status" aria-live="polite"></p>

                <p class="chat-view-note">La IA puede tardar varios segundos en responder.</p>
                <div class="chat-input-area">
                    <input type="text" id="ollamaInput" placeholder="Escribe un mensaje para la IA..." onkeypress="if(event.key==='Enter') sendOllamaMessage();">
                    <button type="button" onclick="sendOllamaMessage()" class="chat-send-btn">Enviar</button>
                </div>
            </div>
            @endif
            @endif

            <div id="broadcastView" class="chat-view {{ (($currentUser['role'] ?? 'guest') === 'guest') ? 'active' : '' }}">
                <div class="chat-messages" id="broadcastMessages"></div>
                <div class="chat-input-area" @if (($currentUser['role'] ?? 'user') !== 'admin') style="display:none;" @endif>
                    <input type="text" id="broadcastInput" placeholder="Mensaje para todos..." onkeypress="if(event.key==='Enter') sendBroadcast();">
                    <button type="button" onclick="sendBroadcast()" class="chat-send-btn">Enviar</button>
                </div>
            </div>
        </div>

        <div class="container-main">
            <div class="container-toolbar">
                @if (($currentUser['role'] ?? 'user') === 'guest')
                    <div class="guest-timer-toolbar">
                        <p class="auth-message auth-success" id="guestRemainingLabel" data-guest-remaining="{{ (int) ($guestRemainingSeconds ?? 0) }}">
                            Tiempo restante invitado: calculando...
                        </p>
                    </div>
                @endif

                {{--
                    Selector de escritorios, SOLO para administradores.

                    Permite probar los escritorios sin crear una cuenta por cada
                    uno. Un usuario normal no lo ve, y aunque forzara la peticion
                    el proxy le devuelve 403: el permiso se comprueba en el
                    servidor.

                    Vive dentro de la barra para que quede en la misma linea que
                    los botones: a la izquierda el selector, a la derecha las
                    acciones.
                --}}
                @if (($currentUser['role'] ?? 'user') === 'admin')
                    <div class="admin-desktop-picker">
                        <span class="admin-desktop-picker-label">Probar escritorio:</span>

                        {{--
                            En un telefono muchos botones ocupan varias filas y
                            empujan el escritorio hacia abajo, asi que en pantalla
                            estrecha se usa una lista desplegable. Los botones se
                            ocultan por CSS (ambos llaman a loadAdminDesktop).
                        --}}
                        <select class="admin-desktop-select"
                                onchange="loadAdminDesktop(this.value)"
                                aria-label="Probar escritorio">
                            @foreach (($adminDesktops ?? []) as $desktop)
                                <option value="{{ $desktop['name'] }}"{{ $desktop['name'] === ($currentDesktop ?? '') ? ' selected' : '' }}>{{ $desktop['name'] }}</option>
                            @endforeach
                        </select>

                        @foreach (($adminDesktops ?? []) as $desktop)
                            <button type="button"
                                    class="admin-desktop-btn{{ $desktop['name'] === ($currentDesktop ?? '') ? ' is-current' : '' }}"
                                    data-container="{{ $desktop['name'] }}"
                                    onclick="loadAdminDesktop('{{ $desktop['name'] }}')"
                                    title="Abrir {{ $desktop['name'] }}">
                                {{ $desktop['name'] }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="container-actions">
                    <button type="button" class="container-load-btn chat-toggle" onclick="toggleChat()" id="chatToggle" title="Abrir chat" aria-label="Abrir chat">
                        <span class="chat-icon" aria-hidden="true"></span>
                        <span class="chat-button-label">Chat</span>
                    </button>

                    <button type="button" class="container-load-btn" onclick="loadInIframe(true)">Recargar Contenedor</button>

                    {{--
                        Abrir el escritorio en una ventana aparte: se abre una
                        pagina nuestra (/contenedor/ventana) que pinta el escritorio
                        dentro, para que la barra de direcciones muestre siempre
                        virthub.dpdns.org y nunca ctX.virthub.dpdns.org.
                    --}}
                    <a class="container-load-btn" id="openWindow"
                       href="{{ url('/contenedor/ventana') }}{{ ($currentUser['role'] ?? 'user') === 'admin' && !empty($currentDesktop) ? '?contenedor=' . urlencode((string) $currentDesktop) : '' }}"
                       onclick="return abrirVentanaEscritorio(event)"
                       title="Abrir el escritorio en una ventana aparte">Ventana Nueva</a>
                </div>
            </div>
            {{--
                sandbox sin 'allow-top-navigation': el escritorio no puede salirse
                del marco. Sin esto, KasmVNC puede navegar al nivel superior y el
                usuario acaba en una pestana suelta con la URL del contenedor a la
                vista, fuera de la aplicacion.

                Se conservan los permisos que el escritorio necesita: scripts,
                mismo origen (para su propio almacenamiento), formularios,
                ventanas emergentes y descargas.
            --}}
            <iframe id="viewer" allow="microphone *"
                    sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals"></iframe>
        </div>
    </div>

    <footer>{{ config('virthub.version') }}</footer>
    
    <script>
        const currentUserName = @json($currentUser['username'] ?? 'guest');
        const isGuestChatMode = @json((($currentUser['role'] ?? 'guest') === 'guest'));
        const chatNotificationSoundUrl = @json(asset('sounds/chat-notificacion.mp3'));
        const ollamaVisible = @json((bool) (($currentUser['role'] ?? 'user') === 'admin'));
        const ollamaEnabled = @json((bool) ($ollamaEnabled ?? false));
        const ollamaModelName = @json((string) ($ollamaModel ?? 'llama3.1'));
        const ollamaChatUser = 'ollama';
        
        let chatNotificationAudio = null;
        
        function initChatNotificationAudio() {
            if (!chatNotificationAudio) {
                // Intentar usar el elemento de audio del DOM primero
                const audioElement = document.getElementById('chatNotificationAudio');
                if (audioElement) {
                    chatNotificationAudio = audioElement;
                } else {
                    // Fallback: crear audio programáticamente
                    chatNotificationAudio = new Audio(chatNotificationSoundUrl);
                    chatNotificationAudio.preload = 'auto';
                }
                chatNotificationAudio.volume = 0.75;
            }
            return chatNotificationAudio;
        }

        function getUserKey() {
            return currentUserName;
        }

        function getThemeStorageKey() {
            return 'virthub_dark_mode_' + getUserKey();
        }

        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function apiFetch(url, options = {}) {
            const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});

            if (options.method && options.method !== 'GET' && options.method !== 'HEAD') {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = getCsrfToken();
            }

            return fetch(url, Object.assign({}, options, { headers }));
        }

        function ensureToastStack() {
            let stack = document.getElementById('chatToastStack');

            if (!stack) {
                stack = document.createElement('div');
                stack.id = 'chatToastStack';
                stack.className = 'chat-toast-stack';
                document.body.appendChild(stack);
            }

            return stack;
        }

        function showChatToast(title, body) {
            const stack = ensureToastStack();
            const toast = document.createElement('div');
            toast.className = 'chat-toast';

            const toastTitle = document.createElement('span');
            toastTitle.className = 'chat-toast-title';
            toastTitle.textContent = title;

            const toastBody = document.createElement('span');
            toastBody.className = 'chat-toast-body';
            toastBody.textContent = body;

            toast.appendChild(toastTitle);
            toast.appendChild(toastBody);
            stack.appendChild(toast);

            window.setTimeout(() => {
                toast.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
            }, 3200);

            window.setTimeout(() => {
                toast.remove();
            }, 3600);
        }

        /*
         * Sonido de notificacion.
         *
         * Tres cosas que antes fallaban:
         *  - poner currentTime antes de que el audio tenga datos lanza un error y
         *    el sonido no se oia nunca la primera vez;
         *  - si llegaban mensajes de varios contactos a la vez, sonaban en ráfaga;
         *  - sonaba tambien mientras leias esa misma conversacion.
         */
        let ultimoSonidoChat = 0;

        function playChatNotificationSound() {
            const ahora = Date.now();

            // Un solo aviso cada 1,5 s: evita la ráfaga de varias conversaciones.
            if (ahora - ultimoSonidoChat < 1500) {
                return;
            }

            ultimoSonidoChat = ahora;

            try {
                const audio = initChatNotificationAudio();

                const sonar = () => {
                    try {
                        // Solo se rebobina si el audio ya tiene datos: si no,
                        // lanzaría un error y no sonaría nunca la primera vez.
                        if (audio.readyState >= 1) {
                            audio.currentTime = 0;
                        }

                        audio.volume = 0.75;

                        const playPromise = audio.play();

                        if (playPromise !== undefined) {
                            playPromise.catch(() => {});
                        }
                    } catch (error) {
                        // Sin sonido no pasa nada: el aviso visual sigue.
                    }
                };

                if (audio.readyState >= 2) {
                    sonar();
                } else {
                    audio.load();
                    audio.addEventListener('canplay', sonar, { once: true });
                }
            } catch (error) {
                // Sin sonido no pasa nada.
            }
        }

        function unlockChatNotificationAudio() {
            try {
                const audio = initChatNotificationAudio();
                audio.play().then(() => {
                    audio.pause();
                    audio.currentTime = 0;
                }).catch(() => {});
            } catch (error) {}

        }
        function showChatNotification(title, body) {
            playChatNotificationSound();

            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, {
                    body,
                    silent: true,
                });
                return;
            }

            showChatToast(title, body);
        }

        function requestChatNotificationPermission() {
            if (!('Notification' in window)) {
                return;
            }

            if (Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {});
            }
        }

        function ensureNotificationContainer() {
            let container = document.getElementById('chatFloatingNotificationContainer');
            
            if (!container) {
                container = document.createElement('div');
                container.id = 'chatFloatingNotificationContainer';
                container.className = 'chat-notification-container container-position';
                document.body.appendChild(container);
            }
            
            return container;
        }

        function showFloatingNotification(sender, message, senderProfileImagePath) {
            playChatNotificationSound();
            const container = ensureNotificationContainer();
            
            const notification = document.createElement('div');
            notification.className = 'chat-notification-item';

            const avatar = document.createElement('div');
            avatar.className = 'chat-notification-avatar';
            
            if (senderProfileImagePath) {
                const img = document.createElement('img');
                img.src = senderProfileImagePath.charAt(0) === '/' ? senderProfileImagePath : '/' + senderProfileImagePath;
                img.alt = sender;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.borderRadius = '50%';
                img.style.objectFit = 'cover';
                avatar.appendChild(img);
            } else {
                avatar.textContent = (sender || 'U').charAt(0).toUpperCase();
            }

            const content = document.createElement('div');
            content.className = 'chat-notification-content';

            const senderName = document.createElement('div');
            senderName.className = 'chat-notification-sender';
            senderName.textContent = sender || 'Nuevo mensaje';

            const messageText = document.createElement('div');
            messageText.className = 'chat-notification-message';
            messageText.textContent = message || 'Tienes un nuevo mensaje';

            content.appendChild(senderName);
            content.appendChild(messageText);

            const closeBtn = document.createElement('button');
            closeBtn.className = 'chat-notification-close';
            closeBtn.textContent = '×';
            closeBtn.type = 'button';
            closeBtn.onclick = () => {
                notification.classList.add('removing');
                setTimeout(() => notification.remove(), 300);
            };

            notification.appendChild(avatar);
            notification.appendChild(content);
            notification.appendChild(closeBtn);
            container.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('removing');
                setTimeout(() => notification.remove(), 300);
            }, 5500);
        }

        function safeTimeLabel(value) {
            if (!value) return '';

            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return '';

            return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }

        /*
         * Estado del chat entre personas.
         *
         * chatEscribiendo guarda quienes estan escribiendo (lo dice el servidor al
         * refrescar la conversacion) y ultimoAvisoEscribiendo sirve para no mandar
         * el aviso en cada tecla.
         */
        const chatEscribiendo = {};
        let ultimoAvisoEscribiendo = 0;

        function avisarEscribiendo() {
            if (!currentChatUser || currentChatUser === ollamaChatUser) return;

            const ahora = Date.now();
            if (ahora - ultimoAvisoEscribiendo < 2500) return;

            ultimoAvisoEscribiendo = ahora;

            apiFetch(`/chat/conversation/${encodeURIComponent(currentChatUser)}/escribiendo`, {
                method: 'POST',
                body: JSON.stringify({}),
            }).catch(() => {});
        }

        /** Burbuja de "esta escribiendo" al final de la conversacion. */
        function pintarEscribiendo(usuario) {
            const contenedor = document.getElementById('chatMessages');
            if (!contenedor) return;

            const previa = document.getElementById('chatEscribiendo');
            if (previa) previa.remove();

            if (!usuario || !chatEscribiendo[usuario]) return;
            if (document.getElementById('messagesView')?.classList.contains('active') === false) return;

            const burbuja = document.createElement('div');
            burbuja.id = 'chatEscribiendo';
            burbuja.className = 'ia-escribiendo';
            burbuja.innerHTML =
                '<span class="ia-puntos" aria-hidden="true"><i></i><i></i><i></i></span>' +
                '<span class="ia-escribiendo-texto"></span>';

            burbuja.querySelector('.ia-escribiendo-texto').textContent = usuario + ' está escribiendo…';

            contenedor.appendChild(burbuja);
            contenedor.scrollTop = contenedor.scrollHeight;
        }

        function renderChatMessages(container, messages, emptyText, ownUser, messageLabelPrefix = '') {
            if (!container) return;

            container.innerHTML = '';

            if (!messages || messages.length === 0) {
                const empty = document.createElement('p');
                empty.style.textAlign = 'center';
                empty.style.color = 'var(--vh-text-soft)';
                empty.style.fontSize = '12px';
                empty.style.padding = '20px 10px';
                empty.textContent = emptyText;
                container.appendChild(empty);
                return;
            }

            messages.forEach(message => {
                const wrapper = document.createElement('div');
                wrapper.className = 'chat-message' + ((message.from || '') === ownUser ? ' own' : '');

                const header = document.createElement('div');
                header.className = 'chat-message-header';

                const sender = message.from || '';
                const senderProfile = { username: sender, profile_image_path: message.profile_image_path || null };

                const avatarNode = buildAvatarNode(senderProfile, 'chat-message-avatar');
                header.appendChild(avatarNode);

                const body = document.createElement('p');
                const messageText = message.message || '';

                if (messageLabelPrefix) {
                    const strong = document.createElement('strong');
                    strong.textContent = messageLabelPrefix;
                    body.appendChild(strong);
                    body.appendChild(document.createTextNode(' '));
                }

                body.appendChild(document.createTextNode(messageText));

                const meta = document.createElement('small');
                const timeLabel = safeTimeLabel(message.created_at);
                meta.textContent = timeLabel ? `${sender}${sender ? ' • ' : ''}${timeLabel}` : sender;

                // Marca de visto: solo en mis mensajes, y solo si el otro ya lo abrió.
                if (ownUser && sender === ownUser && message.read_at && !messageLabelPrefix) {
                    const visto = document.createElement('span');
                    visto.className = 'chat-visto';
                    visto.textContent = ' ✓ Visto';
                    meta.appendChild(visto);
                }

                wrapper.appendChild(header);
                wrapper.appendChild(body);
                wrapper.appendChild(meta);
                container.appendChild(wrapper);
            });

            container.scrollTop = container.scrollHeight;
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

        function loadInIframe(force = false) {
            const iframe = document.getElementById('viewer');
            if (!iframe) return;

            if (force || !iframe.src) {
                // La aplicacion decide el escritorio del usuario y redirige a su
                // subdominio (ct0, ct2...), donde Caddy comprueba la sesion otra
                // vez antes de entregarlo.
                iframe.src = '/contenedor/launch';
            }
        }

        /**
         * Solo administradores: carga el escritorio indicado en el iframe.
         *
         * El nombre viaja como parametro y el servidor comprueba que sea admin
         * antes de emitir el pase. Aqui no se decide nada: si un usuario normal
         * llamara a esta funcion, el proxy responderia 403.
         */
        function loadAdminDesktop(nombre) {
            const iframe = document.getElementById('viewer');
            if (!iframe || !nombre) return;

            iframe.src = '/contenedor/launch?contenedor=' + encodeURIComponent(nombre);

            // Marcar cual esta abierto: los botones en escritorio y la lista
            // desplegable en movil.
            document.querySelectorAll('.admin-desktop-btn').forEach(btn => {
                btn.classList.toggle('is-current', btn.dataset.container === nombre);
            });

            const lista = document.querySelector('.admin-desktop-select');
            if (lista) {
                lista.value = nombre;
            }

            // El boton de ventana nueva debe abrir el MISMO escritorio que se
            // esta viendo, no el que el usuario tenga asignado por defecto.
            const abrir = document.getElementById('openWindow');
            if (abrir) {
                abrir.href = '/contenedor/ventana?contenedor=' + encodeURIComponent(nombre);
            }
        }

        /**
         * Abre el escritorio en una ventana aparte.
         *
         * Se usa una ventana con tamano fijo y sin barra de herramientas, para
         * que se parezca a una aplicacion y no a una pestana mas. El navegador
         * decide que marco muestra; la direccion visible sera la nuestra, porque
         * la pagina que se abre vive en este mismo dominio.
         *
         * Devuelve false para que el enlace no navegue ademas en esta pestana.
         */
        function abrirVentanaEscritorio(evento) {
            const enlace = document.getElementById('openWindow');
            if (!enlace) return true;

            // Siempre se cancela la navegacion del enlace. Si no, y la ventana
            // llega a abrirse, el enlace ademas abriria una pestana: pasaba justo
            // por el 'noopener' de abajo, que hace que window.open devuelva null.
            if (evento) evento.preventDefault();

            const ancho = Math.min(1500, Math.max(900, Math.round(window.screen.availWidth * 0.9)));
            const alto = Math.min(950, Math.max(600, Math.round(window.screen.availHeight * 0.9)));
            const izquierda = Math.max(0, Math.round((window.screen.availWidth - ancho) / 2));
            const arriba = Math.max(0, Math.round((window.screen.availHeight - alto) / 2));

            // Un nombre fijo hace que se reutilice la MISMA ventana en vez de
            // abrir una nueva cada vez que se pulsa.
            //
            // Sin 'noopener' en las opciones: con el, window.open devuelve null y
            // no se puede saber si la ventana se abrio. El aislamiento se
            // consigue igual anulando opener a mano justo despues.
            const ventana = window.open(
                enlace.href,
                'virthub_escritorio',
                `popup=yes,width=${ancho},height=${alto},left=${izquierda},top=${arriba}`
            );

            if (ventana) {
                // La ventana nueva no debe poder tocar esta por window.opener.
                try { ventana.opener = null; } catch (error) { /* da igual */ }
                ventana.focus();
            } else {
                // El navegador bloqueo la ventana emergente. En vez de dejar el
                // boton sin hacer nada (o abrir una pestana mas), se carga la
                // misma pagina en esta ventana.
                window.location.href = enlace.href;
            }

            return false;
        }

        function getFullscreenElement() {
            return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
        }

        function syncFullscreenButtonState() {
            const button = document.getElementById('fullscreenToggle');
            if (!button) return;

            const isFullscreen = !!getFullscreenElement();
            button.textContent = isFullscreen ? 'Salir Pantalla Completa' : 'Pantalla Completa';
            button.title = isFullscreen ? 'Salir de pantalla completa' : 'Entrar en pantalla completa';
            button.setAttribute('aria-pressed', isFullscreen ? 'true' : 'false');
        }

        async function toggleFullscreen() {
            const target = document.querySelector('.container-main');
            if (!target) return;

            try {
                if (!getFullscreenElement()) {
                    if (target.requestFullscreen) {
                        await target.requestFullscreen();
                    } else if (target.webkitRequestFullscreen) {
                        target.webkitRequestFullscreen();
                    } else if (target.msRequestFullscreen) {
                        target.msRequestFullscreen();
                    }
                } else if (document.exitFullscreen) {
                    await document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            } catch (error) {
                console.log('No se pudo cambiar a pantalla completa:', error);
            } finally {
                syncFullscreenButtonState();
            }
        }

        function startGuestCountdown() {
            const label = document.getElementById('guestRemainingLabel');
            if (!label) return;

            let remaining = Number(label.dataset.guestRemaining || 0);
            let avisadoUnMinuto = false;

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

                // Aviso al minuto: se muestra una sola vez. Se comprueba con
                // <= 60 y > 0 para no repetirlo en cada tic.
                if (!avisadoUnMinuto && remaining <= 60 && remaining > 0) {
                    avisadoUnMinuto = true;
                    mostrarAvisoInvitado('La sesion esta por terminar', 'Te queda 1 minuto. Cuando llegue a cero, este escritorio se cerrara y se borrara su informacion personal.');
                }

                if (remaining <= 0) {
                    clearInterval(timer);
                    label.textContent = 'Tiempo de invitado expirado.';
                    mostrarAvisoInvitado('Sesion finalizada', 'Tu tiempo como invitado termino y se borro la informacion personal de este escritorio.');
                    limpiarEscritorioInvitado();
                }
            }, 1000);
        }

        /* Aviso flotante para el invitado (mismo estilo de tarjeta que el resto). */
        function mostrarAvisoInvitado(titulo, texto) {
            const modal = document.getElementById('guestWarningModal');
            if (!modal) return;

            const h = document.getElementById('guestWarningTitle');
            const p = document.getElementById('guestWarningText');
            if (h) h.textContent = titulo;
            if (p) p.textContent = texto;

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function cerrarAvisoInvitado() {
            const modal = document.getElementById('guestWarningModal');
            if (!modal) return;

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        /*
         * Pide al servidor que borre la informacion personal del escritorio de
         * invitado. Si falla no se avisa al usuario: es una limpieza, no una
         * accion que el necesite saber.
         */
        async function limpiarEscritorioInvitado() {
            try {
                await fetch('/invitado/limpiar', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });
            } catch (error) {
                // Sin ruido: la limpieza se reintenta en el siguiente acceso.
            }
        }

        function toggleChat() {
            const chatPanel = document.getElementById('chatPanel');
            const containerWrapper = document.querySelector('.container-wrapper');
            if (!chatPanel) return;

            chatPanel.classList.toggle('is-open');

            if (containerWrapper) {
                containerWrapper.classList.toggle('chat-open', chatPanel.classList.contains('is-open'));
            }

            unlockChatNotificationAudio();

            if (chatPanel.classList.contains('is-open')) {
                requestChatNotificationPermission();

                /*
                 * Al abrir, siempre la pestaña de usuarios: es lo primero que se
                 * necesita, elegir con quién hablar. Los invitados solo tienen
                 * anuncios, así que ellos se quedan ahí.
                 */
                switchChatTab(isGuestChatMode ? 'broadcast' : 'users');
            }
        }

        function syncChatLayoutState() {
            const chatPanel = document.getElementById('chatPanel');
            const containerWrapper = document.querySelector('.container-wrapper');

            if (!chatPanel || !containerWrapper) return;

            containerWrapper.classList.toggle('chat-open', chatPanel.classList.contains('is-open'));
        }

        let currentChatUser = null;
        const chatSnapshots = {
            conversations: {},
            broadcast: 0,
        };
        let chatContacts = [];

        async function switchChatTab(tab) {
            if (isGuestChatMode) {
                tab = 'broadcast';
            }

            document.querySelectorAll('.chat-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.chat-view').forEach(view => view.classList.remove('active'));

            const tabButton = document.querySelector(`[data-tab="${tab}"]`);
            const tabView = document.getElementById(tab + 'View');

            if (tabButton) tabButton.classList.add('active');
            if (tabView) tabView.classList.add('active');

            if (tab === 'users') {
                await loadUsersList();
            }

            if (tab === 'friendRequests') {
                await loadFriendRequests();
            }

            if (tab === 'broadcast') {
                await loadBroadcastMessages();
            }

            if (tab === 'ollama') {
                await loadOllamaMessages();
                iniciarMonitorIA();
            }
        }

        function openOllamaChat() {
            if (!ollamaVisible) {
                return;
            }

            currentChatUser = ollamaChatUser;
            switchChatTab('ollama');
        }

        function chatConversationTitle(username) {
            return username === ollamaChatUser ? 'Chat con IA' : `Chat con ${username}`;
        }

        function userInitial(username) {
            const value = String(username || 'U').trim();
            return (value.charAt(0) || 'U').toUpperCase();
        }

        function buildAvatarNode(profile, className = 'chat-user-avatar') {
            const avatar = document.createElement('span');
            avatar.className = className;

            const imagePath = (profile && profile.profile_image_path) ? String(profile.profile_image_path) : '';
            if (imagePath) {
                // Los adjuntos se sirven por el enlace public/storage; sin este
                // prefijo la URL da 404 porque el archivo no esta en la raiz web.
                const prefijada = imagePath.indexOf('storage/') === 0
                    ? imagePath
                    : 'storage/' + imagePath.replace(/^\/+/, '');

                const img = document.createElement('img');
                img.src = '/' + prefijada;
                img.alt = 'avatar';
                img.loading = 'lazy';

                // Si el archivo del avatar ya no existe, se cae a la inicial en
                // lugar de dejar un icono de imagen rota.
                img.addEventListener('error', () => {
                    avatar.textContent = userInitial(profile && profile.username ? profile.username : 'U');
                });

                avatar.appendChild(img);
                return avatar;
            }

            avatar.textContent = userInitial(profile && profile.username ? profile.username : 'U');
            return avatar;
        }

        async function loadUsersList() {
            const list = document.getElementById('chatUsersList');
            if (!list) return;

            list.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando usuarios...</p>';

            try {
                const response = await apiFetch('/chat/users');
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error || 'No se pudieron cargar los usuarios');
                }

                chatContacts = (payload.users || []).filter(user => user.username !== getUserKey());
                const otherUsers = (payload.users || []).filter(user => user.username !== getUserKey());

                if (otherUsers.length === 0) {
                    list.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">No hay otros usuarios disponibles.</p>';
                    return;
                }

                list.innerHTML = '';
                otherUsers.forEach(user => {
                    const accountActive = user.account_active !== undefined ? !!user.account_active : !!user.is_active;
                    const isOnline = (user.presence_status || (accountActive ? 'online' : 'offline')) === 'online';

                    const item = document.createElement('div');
                    item.className = 'chat-user-item';
                    if (!accountActive) {
                        item.classList.add('inactive');
                    } else {
                        item.addEventListener('click', () => selectChatUser(user.username));
                    }

                    const main = document.createElement('div');
                    main.className = 'chat-user-main';

                    main.appendChild(buildAvatarNode({
                        username: user.username,
                        profile_image_path: user.profile_image_path || null,
                    }));

                    const name = document.createElement('span');
                    name.className = 'chat-user-name';
                    name.textContent = user.name || user.username;
                    main.appendChild(name);

                    const status = document.createElement('span');
                    status.className = 'chat-user-status ' + (isOnline ? 'active' : 'inactive');
                    status.title = isOnline ? 'Conectado recientemente' : 'Desconectado';
                    main.appendChild(status);

                    item.appendChild(main);
                    list.appendChild(item);
                });
            } catch (error) {
                list.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        async function loadFriendRequests() {
            const list = document.getElementById('chatFriendRequestsList');
            if (!list || isGuestChatMode) return;

            list.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando solicitudes...</p>';

            try {
                const response = await apiFetch('/chat/friend-requests');
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.error || 'No se pudieron cargar las solicitudes');

                const requests = payload.requests || [];
                list.innerHTML = '';

                if (requests.length === 0) {
                    list.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">No tienes solicitudes pendientes.</p>';
                    return;
                }

                requests.forEach(friendRequest => {
                    const item = document.createElement('div');
                    item.className = 'chat-user-item';

                    const name = document.createElement('span');
                    name.className = 'chat-user-name';
                    name.textContent = friendRequest.name || friendRequest.from || 'Usuario';

                    const actions = document.createElement('div');
                    actions.style.display = 'flex';
                    actions.style.gap = '6px';

                    ['accepted', 'declined'].forEach(status => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'chat-send-btn';
                        button.textContent = status === 'accepted' ? 'Aceptar' : 'Rechazar';
                        button.addEventListener('click', () => respondToFriendRequest(friendRequest.id, status));
                        actions.appendChild(button);
                    });

                    item.appendChild(name);
                    item.appendChild(actions);
                    list.appendChild(item);
                });
            } catch (error) {
                list.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        async function respondToFriendRequest(requestId, status) {
            try {
                const response = await apiFetch(`/chat/friend-requests/${encodeURIComponent(requestId)}`, {
                    method: 'POST',
                    body: JSON.stringify({ status })
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.error || 'No se pudo responder la solicitud');

                await loadFriendRequests();
                if (status === 'accepted') await loadUsersList();
            } catch (error) {
                alert(error.message);
            }
        }

        async function refreshConversationSilently(username, shouldNotify = false) {
            const messages = await loadConversationMessages(username);
            const previousCount = chatSnapshots.conversations[username] || 0;

            chatSnapshots.conversations[username] = messages.length;

            if (shouldNotify && messages.length > previousCount) {
                const newMessages = messages.slice(previousCount);
                const incoming = newMessages.filter(message => (message.from || '') !== getUserKey());
                const isChatPanelOpen = document.getElementById('chatPanel')?.classList.contains('is-open');
                const isConversationOpen = currentChatUser === username;
                const messagesTabActive = document.getElementById('messagesView')?.classList.contains('active') !== false;

                /*
                 * "Leyendo" = panel abierto, es esta conversacion, pestaña de
                 * mensajes activa y ventana a la vista. Solo entonces se evita la
                 * notificacion del navegador (el sonido se mantiene).
                 */
                const leyendo = !document.hidden && isChatPanelOpen && isConversationOpen && messagesTabActive;

                incoming.forEach(message => {
                    /*
                     * ¿Estás leyendo esta conversación? Entonces suena el aviso,
                     * pero no se lanza notificación del navegador ni se sube el
                     * contador: ya lo estás viendo.
                     */
                    if (leyendo) {
                        playChatNotificationSound();
                        return;
                    }

                    showChatNotification(`Nuevo mensaje de ${message.from || username}`, message.message || 'Tienes un nuevo mensaje.');
                    showFloatingNotification(message.from || username, message.message || 'Tienes un nuevo mensaje.', message.profile_image_path || null);
                });
            }

            if (currentChatUser === username) {
                const messagesDiv = document.getElementById('chatMessages');
                renderChatMessages(messagesDiv, messages, chatConversationTitle(username), getUserKey());
            pintarEscribiendo(username);
            }

            return messages;
        }

        async function loadConversationMessages(username) {
            const response = await apiFetch(`/chat/conversation/${encodeURIComponent(username)}`);
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo cargar la conversación');
            }

            chatEscribiendo[username] = !!payload.escribiendo;

            return payload.messages || [];
        }

        async function selectChatUser(username) {
            currentChatUser = username;
            await switchChatTab('messages');

            const messagesDiv = document.getElementById('chatMessages');
            if (!messagesDiv) return;

            messagesDiv.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando mensajes...</p>';

            try {
                const messages = await refreshConversationSilently(username, false);
                renderChatMessages(messagesDiv, messages, chatConversationTitle(username), getUserKey());
            pintarEscribiendo(username);
            } catch (error) {
                messagesDiv.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        async function sendChatMessage() {
            if (isGuestChatMode) return;

            const input = document.getElementById('chatInput');
            if (!input || !input.value.trim() || !currentChatUser) {
                if (!currentChatUser) alert('Selecciona un usuario primero');
                return;
            }

            const messageText = input.value.trim();
            const inicioConsulta = performance.now();
            let avisoTarda = null;

            try {
                const response = await apiFetch(`/chat/conversation/${encodeURIComponent(currentChatUser)}`, {
                    method: 'POST',
                    body: JSON.stringify({ message: messageText }),
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error || 'No se pudo enviar el mensaje');
                }

                const messagesDiv = document.getElementById('chatMessages');
                const messages = await refreshConversationSilently(currentChatUser, false);
                renderChatMessages(messagesDiv, messages, chatConversationTitle(currentChatUser), getUserKey());
                pintarEscribiendo(currentChatUser);
                chatSnapshots.conversations[currentChatUser] = messages.length;

                input.value = '';
                input.focus();

                // Velocidad aproximada: caracteres por segundo de esta respuesta.
                const segundos = (performance.now() - inicioConsulta) / 1000;
                const ultima = messages && messages.length ? String(messages[messages.length - 1].message || '') : '';

                if (segundos > 0.5 && ultima.length > 0) {
                    const chip = document.getElementById('iaMonVelocidadChip');
                    if (chip) {
                        chip.hidden = false;
                        document.getElementById('iaMonVelocidad').textContent = (ultima.length / segundos).toFixed(1) + ' car/s';
                    }
                }
            } catch (error) {
                alert(error.message);
            }
        }

        async function loadBroadcastMessages() {
            const messagesDiv = document.getElementById('broadcastMessages');
            if (!messagesDiv) return;

            messagesDiv.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando mensajes...</p>';

            try {
                const response = await apiFetch('/chat/broadcast');
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error || 'No se pudieron cargar las notificaciones');
                }

                const messages = payload.messages || [];
                renderChatMessages(messagesDiv, messages, 'No hay anuncios aún.', getUserKey(), '[ANUNCIO]');

                chatSnapshots.broadcast = messages.length;
            } catch (error) {
                messagesDiv.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        async function loadOllamaMessages() {
            const messagesDiv = document.getElementById('ollamaMessages');
            if (!messagesDiv) return;

            messagesDiv.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Cargando chat de IA...</p>';

            try {
                const messages = await loadConversationMessages(ollamaChatUser);
                chatSnapshots.conversations[ollamaChatUser] = messages.length;
                renderChatMessages(messagesDiv, messages, chatConversationTitle(ollamaChatUser), getUserKey());
            } catch (error) {
                messagesDiv.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        /*
         * Monitor de recursos del panel de IA.
         *
         * Consulta cada dos segundos la GPU y el modelo mientras la pestaña esta
         * abierta. Si el puente de la GPU no responde, el bloque se oculta solo:
         * no debe estorbar ni romper el chat.
         */
        let iaMonitorTimer = null;

        async function refrescarMonitorIA() {
            // Sin la pestaña a la vista no se consulta la GPU.
            if (document.hidden) return;

            const bloque = document.getElementById('iaMonitor');
            if (!bloque) return;

            try {
                const response = await apiFetch('/ia/recursos', { method: 'GET' });
                if (!response.ok) { bloque.hidden = true; return; }

                const datos = await response.json();

                if (!datos.monitor) { bloque.hidden = true; return; }
                bloque.hidden = false;

                if (datos.gpu) {
                    document.getElementById('iaMonGpu').textContent = datos.gpu.util + ' %';
                    document.getElementById('iaMonVram').textContent =
                        (datos.gpu.mem_used / 1024).toFixed(0) + ' / ' + (datos.gpu.mem_total / 1024).toFixed(0) + ' GB';
                    document.getElementById('iaMonTemp').textContent = datos.gpu.temp + ' °C';
                } else {
                    document.getElementById('iaMonGpu').textContent = 'sin datos';
                    document.getElementById('iaMonVram').textContent = '—';
                    document.getElementById('iaMonTemp').textContent = '—';
                }

                const cargados = (datos.ollama && datos.ollama.cargados) || [];
                document.getElementById('iaMonModelo').textContent = cargados.length
                    ? cargados[0].name
                    : (datos.ollama && datos.ollama.modelo) || '—';
            } catch (error) {
                bloque.hidden = true;
            }
        }

        function iniciarMonitorIA() {
            if (!document.getElementById('iaMonitor')) return;
            refrescarMonitorIA();
            if (!iaMonitorTimer) {
                iaMonitorTimer = setInterval(refrescarMonitorIA, 2000);
            }
        }
        /*
         * Indicador de "está respondiendo".
         *
         * Aparece como una burbuja al final del chat mientras el modelo piensa,
         * para que quede claro que la consulta salió y se está trabajando en ella.
         */
        function mostrarEscribiendoIA() {
            const messagesDiv = document.getElementById('ollamaMessages');
            if (!messagesDiv) return;

            let previo = document.getElementById('iaEscribiendo');
            if (previo) previo.remove();

            const burbuja = document.createElement('div');
            burbuja.id = 'iaEscribiendo';
            burbuja.className = 'ia-escribiendo';
            burbuja.innerHTML =
                '<span class="ia-puntos" aria-hidden="true"><i></i><i></i><i></i></span>' +
                '<span class="ia-escribiendo-texto">TutorUTT está escribiendo…</span>';

            messagesDiv.appendChild(burbuja);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function quitarEscribiendoIA() {
            const burbuja = document.getElementById('iaEscribiendo');
            if (burbuja) burbuja.remove();
        }
        /*
         * Borrar el historial de la conversacion con la IA.
         *
         * Sirve para empezar de cero: el modelo deja de arrastrar respuestas
         * anteriores y cualquier personalidad que se hubiera colado.
         */
        /*
         * Borrar el historial de la conversacion con la IA.
         *
         * Todo pasa en la pagina: la confirmacion es una tira en la misma barra y
         * el resultado se muestra en un aviso. Nada de ventanas del navegador, que
         * tapaban el chat y no dejaban ver el motivo si algo fallaba.
         */
        function mostrarAvisoIA(texto, tipo) {
            const aviso = document.getElementById('iaAviso');
            if (!aviso) return;

            aviso.textContent = texto;
            aviso.className = 'ia-aviso ' + (tipo === 'error' ? 'ia-aviso-error' : 'ia-aviso-ok');
            aviso.hidden = false;

            clearTimeout(mostrarAvisoIA.tiempo);
            mostrarAvisoIA.tiempo = setTimeout(() => { aviso.hidden = true; }, 8000);
        }

        function confirmarBorradoIA() {
            const confirmar = document.getElementById('iaConfirmar');
            const boton = document.getElementById('iaBorrarHistorial');
            if (!confirmar) return;

            confirmar.hidden = false;
            if (boton) boton.hidden = true;
        }

        function cancelarBorradoIA() {
            const confirmar = document.getElementById('iaConfirmar');
            const boton = document.getElementById('iaBorrarHistorial');
            if (!confirmar) return;

            confirmar.hidden = true;
            if (boton) boton.hidden = false;
        }

        async function borrarHistorialIA() {
            if (!ollamaVisible) return;

            const confirmar = document.getElementById('iaConfirmar');
            const boton = document.getElementById('iaBorrarHistorial');

            if (confirmar) confirmar.hidden = true;
            if (boton) { boton.hidden = false; boton.disabled = true; }

            try {
                const response = await apiFetch(`/chat/conversation/${encodeURIComponent(ollamaChatUser)}/limpiar`, {
                    method: 'POST',
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));

                    // El detalle ayuda a saber qué pasó: sesión caducada, permisos...
                    let motivo = payload.error;

                    if (!motivo) {
                        if (response.status === 429) {
                            motivo = 'demasiados intentos seguidos, espera un minuto';
                        } else if (response.status === 419) {
                            motivo = 'la sesión caducó, recarga la página';
                        } else {
                            motivo = 'error ' + response.status;
                        }
                    }

                    throw new Error(motivo);
                }

                const messagesDiv = document.getElementById('ollamaMessages');
                if (messagesDiv) {
                    messagesDiv.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">Historial borrado. Escribe un mensaje para empezar.</p>';
                }

                chatSnapshots.conversations[ollamaChatUser] = 0;
                mostrarAvisoIA('Historial borrado. El chat empieza de cero.', 'ok');
            } catch (error) {
                mostrarAvisoIA('No se pudo borrar el historial: ' + error.message, 'error');
            } finally {
                if (boton) boton.disabled = false;
            }
        }
        async function sendOllamaMessage() {
            if (!ollamaVisible) return;

            const input = document.getElementById('ollamaInput');
            const sendBtn = document.querySelector('#ollamaView .chat-send-btn');
            if (!input || !input.value.trim()) {
                return;
            }

            const messageText = input.value.trim();
            const inicioConsulta = performance.now();
            let avisoTarda = null;

            try {
                // Deshabilitar input y botón mientras procesa
                input.disabled = true;
                sendBtn.disabled = true;
                input.placeholder = 'El tutor está escribiendo...';

                mostrarEscribiendoIA();

                /*
                 * El primer mensaje puede tardar: hay que cargar el modelo en
                 * memoria. Pasados unos segundos se avisa, para que nadie piense
                 * que se quedó colgado.
                 */
                avisoTarda = setTimeout(() => {
                    const texto = document.querySelector('#iaEscribiendo .ia-escribiendo-texto');

                    if (texto) {
                        texto.textContent = 'Cargando el modelo… la primera vez tarda más.';
                    }
                }, 12000);

                const response = await apiFetch(`/chat/conversation/${encodeURIComponent(ollamaChatUser)}`, {
                    method: 'POST',
                    body: JSON.stringify({ message: messageText }),
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error || 'No se pudo consultar la IA');
                }

                const messagesDiv = document.getElementById('ollamaMessages');
                const messages = await loadConversationMessages(ollamaChatUser);
                renderChatMessages(messagesDiv, messages, chatConversationTitle(ollamaChatUser), getUserKey());
                chatSnapshots.conversations[ollamaChatUser] = messages.length;

                input.value = '';
                input.focus();

                // Velocidad aproximada: caracteres por segundo de esta respuesta.
                const segundos = (performance.now() - inicioConsulta) / 1000;
                const ultima = messages && messages.length ? String(messages[messages.length - 1].message || '') : '';

                if (segundos > 0.5 && ultima.length > 0) {
                    const chip = document.getElementById('iaMonVelocidadChip');
                    if (chip) {
                        chip.hidden = false;
                        document.getElementById('iaMonVelocidad').textContent = (ultima.length / segundos).toFixed(1) + ' car/s';
                    }
                }
            } catch (error) {
                mostrarAvisoIA(error.message, 'error');
            } finally {
                clearTimeout(avisoTarda);
                quitarEscribiendoIA();

                // Restaurar estado del input y botón
                input.disabled = false;
                sendBtn.disabled = false;
                input.placeholder = 'Escribe un mensaje para la IA...';
            }
        }

        async function sendBroadcast() {
            const input = document.getElementById('broadcastInput');
            const sendBtn = document.querySelector('#broadcastView .chat-send-btn');
            if (!input || !input.value.trim()) return;

            const messageText = input.value.trim();
            const inicioConsulta = performance.now();
            let avisoTarda = null;

            try {
                // Deshabilitar input y botón mientras procesa
                input.disabled = true;
                sendBtn.disabled = true;
                input.placeholder = 'El tutor está escribiendo...';

                mostrarEscribiendoIA();

                /*
                 * El primer mensaje puede tardar: hay que cargar el modelo en
                 * memoria. Pasados unos segundos se avisa, para que nadie piense
                 * que se quedó colgado.
                 */
                avisoTarda = setTimeout(() => {
                    const texto = document.querySelector('#iaEscribiendo .ia-escribiendo-texto');

                    if (texto) {
                        texto.textContent = 'Cargando el modelo… la primera vez tarda más.';
                    }
                }, 12000);

                const response = await apiFetch('/chat/broadcast', {
                    method: 'POST',
                    body: JSON.stringify({ message: messageText }),
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error || 'No se pudo publicar el anuncio');
                }

                await loadBroadcastMessages();
                chatSnapshots.broadcast = (await (await apiFetch('/chat/broadcast')).json()).messages.length;
                input.value = '';
                input.focus();

                // Velocidad aproximada: caracteres por segundo de esta respuesta.
                const segundos = (performance.now() - inicioConsulta) / 1000;
                const ultima = messages && messages.length ? String(messages[messages.length - 1].message || '') : '';

                if (segundos > 0.5 && ultima.length > 0) {
                    const chip = document.getElementById('iaMonVelocidadChip');
                    if (chip) {
                        chip.hidden = false;
                        document.getElementById('iaMonVelocidad').textContent = (ultima.length / segundos).toFixed(1) + ' car/s';
                    }
                }
            } catch (error) {
                mostrarAvisoIA(error.message, 'error');
            } finally {
                clearTimeout(avisoTarda);
                quitarEscribiendoIA();

                // Restaurar estado del input y botón
                input.disabled = false;
                sendBtn.disabled = false;
                input.placeholder = 'Mensaje para todos...';
            }
        }

        function startChatPolling() {
            let notificationsPrimed = false;

            /*
             * Sondeo del chat: una vez por contacto cada 5 segundos.
             *
             * Antes iba cada 2 segundos: con veinte contactos eran diez peticiones
             * por segundo, para siempre. A cinco segundos se nota igual de vivo y
             * el servidor respira.
             */
            setInterval(async () => {
                // Con la pestaña en segundo plano no se pide nada: ni gasta CPU ni datos.
                if (document.hidden) {
                    return;
                }

                try {
                    const shouldNotify = notificationsPrimed;

                    if (!isGuestChatMode) {
                        if (chatContacts.length === 0) {
                            await loadUsersList();
                        }

                        for (const contact of chatContacts) {
                            await refreshConversationSilently(contact.username, shouldNotify);
                        }
                    }

                    const response = await apiFetch('/chat/broadcast');
                    const payload = await response.json();

                    if (response.ok) {
                        const messages = payload.messages || [];
                        const previousCount = chatSnapshots.broadcast || 0;

                        if (messages.length > previousCount) {
                            const newMessages = messages.slice(previousCount);
                            if (shouldNotify) {
                                newMessages.forEach(message => {
                                    if ((message.from || '') !== getUserKey()) {
                                        showChatNotification(`Broadcast de ${message.from || 'Sistema'}`, message.message || 'Nuevo anuncio disponible.');
                                        const isChatPanelOpen = document.getElementById('chatPanel')?.classList.contains('is-open');
                                        const isBroadcastViewActive = document.getElementById('broadcastView')?.classList.contains('active');
                                        const shouldShowFloatingNotification = !isChatPanelOpen || (isChatPanelOpen && !isBroadcastViewActive);
                                        if (shouldShowFloatingNotification) {
                                            showFloatingNotification(message.from || 'Sistema', message.message || 'Nuevo anuncio disponible.', message.profile_image_path || null);
                                        }
                                    }
                                });
                            }

                            chatSnapshots.broadcast = messages.length;

                            if (document.getElementById('broadcastView')?.classList.contains('active')) {
                                renderChatMessages(document.getElementById('broadcastMessages'), messages, 'No hay anuncios aún.', getUserKey(), '[ANUNCIO]');
                            }
                        }
                    }

                    notificationsPrimed = true;
                } catch (error) {
                    // Silenciar errores temporales de polling.
                }
            }, 5000);
        }

        window.addEventListener('DOMContentLoaded', async () => {
            applySidebarState();
            syncFullscreenButtonState();
            loadInIframe();
            syncChatLayoutState();
            startGuestCountdown();
            unlockChatNotificationAudio();
            await loadBroadcastMessages();
            if (!isGuestChatMode) {
                await loadUsersList();
            } else {
                await switchChatTab('broadcast');
            }
            startChatPolling();
        });

        /*
         * Aviso de "está escribiendo" al teclear en el chat de usuarios.
         * El freno está dentro de avisarEscribiendo (una vez cada 2,5 s).
         */
        document.getElementById('chatInput')?.addEventListener('input', avisarEscribiendo);

        document.addEventListener('click', () => {
            unlockChatNotificationAudio();
        }, { once: true });

        document.addEventListener('fullscreenchange', syncFullscreenButtonState);
        document.addEventListener('webkitfullscreenchange', syncFullscreenButtonState);
        document.addEventListener('MSFullscreenChange', syncFullscreenButtonState);
    </script>
    {{--
        Aviso del invitado.

        Aparece cuando queda un minuto y tambien al llegar a cero. Solo es un
        mensaje con un boton para cerrarlo, sin formularios.
    --}}
    @if (($currentUser['role'] ?? 'user') === 'guest')
        <div class="guest-warning-modal" id="guestWarningModal" aria-hidden="true">
            <div class="guest-warning-card" role="dialog" aria-modal="true" aria-labelledby="guestWarningTitle">
                <h3 id="guestWarningTitle">La sesion esta por terminar</h3>
                <p id="guestWarningText">Te queda 1 minuto.</p>
                <button type="button" class="guest-warning-close" onclick="cerrarAvisoInvitado()">Entendido</button>
            </div>
        </div>
    @endif
</body>
</html>
