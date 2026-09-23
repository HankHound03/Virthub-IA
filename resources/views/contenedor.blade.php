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

                <button type="button" class="container-load-btn chat-toggle" onclick="toggleChat()" id="chatToggle" title="Abrir chat" aria-label="Abrir chat">
                    <span class="chat-icon" aria-hidden="true"></span>
                    <span class="chat-button-label">Chat</span>
                </button>

                <button type="button" class="container-load-btn" onclick="loadInIframe(true)">Recargar Contenedor</button>
                <button type="button" class="container-load-btn" id="fullscreenToggle" onclick="toggleFullscreen()" title="Entrar en pantalla completa" aria-label="Alternar pantalla completa" aria-pressed="false">Pantalla Completa</button>
            </div>
            <iframe id="viewer" allow="microphone *"></iframe>
        </div>
    </div>

    <footer>Virthub 1.0</footer>
    
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

        function playChatNotificationSound() {
            try {
                const audio = initChatNotificationAudio();
                audio.currentTime = 0;
                audio.volume = 0.75;
                const playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => {
                        console.log('Notificación de audio no disponible:', error);
                    });
                }
            } catch (error) {
                console.log('Error al reproducir sonido:', error);
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
                iframe.src = '/contenedor/launch';
            }
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
                const img = document.createElement('img');
                img.src = imagePath.charAt(0) === '/' ? imagePath : '/' + imagePath;
                img.alt = 'avatar';
                img.loading = 'lazy';
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
                const shouldShowFloatingNotification = !isChatPanelOpen || (isChatPanelOpen && !isConversationOpen);

                incoming.forEach(message => {
                    showChatNotification(`Nuevo mensaje de ${message.from || username}`, message.message || 'Tienes un nuevo mensaje.');
                    if (shouldShowFloatingNotification) {
                        showFloatingNotification(message.from || username, message.message || 'Tienes un nuevo mensaje.', message.profile_image_path || null);
                    }
                });
            }

            if (currentChatUser === username) {
                const messagesDiv = document.getElementById('chatMessages');
                renderChatMessages(messagesDiv, messages, chatConversationTitle(username), getUserKey());
            }

            return messages;
        }

        async function loadConversationMessages(username) {
            const response = await apiFetch(`/chat/conversation/${encodeURIComponent(username)}`);
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo cargar la conversación');
            }

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
                chatSnapshots.conversations[currentChatUser] = messages.length;

                input.value = '';
                input.focus();
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

        async function sendOllamaMessage() {
            if (!ollamaVisible) return;

            const input = document.getElementById('ollamaInput');
            const sendBtn = document.querySelector('#ollamaView .chat-send-btn');
            if (!input || !input.value.trim()) {
                return;
            }

            const messageText = input.value.trim();

            try {
                // Deshabilitar input y botón mientras procesa
                input.disabled = true;
                sendBtn.disabled = true;
                input.placeholder = 'Ollama está escribiendo...';

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
            } catch (error) {
                alert(error.message);
            } finally {
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

            try {
                // Deshabilitar input y botón mientras procesa
                input.disabled = true;
                sendBtn.disabled = true;
                input.placeholder = 'Enviando anuncio...';

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
            } catch (error) {
                alert(error.message);
            } finally {
                // Restaurar estado del input y botón
                input.disabled = false;
                sendBtn.disabled = false;
                input.placeholder = 'Mensaje para todos...';
            }
        }

        function startChatPolling() {
            let notificationsPrimed = false;

            setInterval(async () => {
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
            }, 2000);
        }

        window.addEventListener('DOMContentLoaded', async () => {
            applySidebarState();
            applyThemeState();
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

        document.addEventListener('click', () => {
            unlockChatNotificationAudio();
        }, { once: true });

        document.addEventListener('fullscreenchange', syncFullscreenButtonState);
        document.addEventListener('webkitfullscreenchange', syncFullscreenButtonState);
        document.addEventListener('MSFullscreenChange', syncFullscreenButtonState);
    </script>
</body>
</html>