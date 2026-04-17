<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
                    <button type="button" onclick="location.href='{{ url('/foro') }}'">Foro</button>
                    @if (($currentUser['role'] ?? 'user') === 'admin')
                        <button type="button" onclick="location.href='{{ url('/admin/users') }}'">Panel Admin</button>
                    @endif
                </div>
            </div>
            <div class="chat-toggle" onclick="toggleChat()" id="chatToggle" title="Abrir chat" aria-label="Abrir chat">
                <span class="chat-icon" aria-hidden="true"></span>
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

    <div class="container-wrapper">
        <div class="chat-panel" id="chatPanel">
            <div class="chat-header">
                <h3>Chat</h3>
                <button type="button" class="chat-close" onclick="toggleChat()">×</button>
            </div>
            
            <div class="chat-tabs" id="chatTabs">
                <button type="button" class="chat-tab-btn active" onclick="switchChatTab('messages')" data-tab="messages">Mensajes</button>
                <button type="button" class="chat-tab-btn" onclick="switchChatTab('users')" data-tab="users">Usuarios</button>
                <button type="button" class="chat-tab-btn" onclick="switchChatTab('broadcast')" data-tab="broadcast">@if (($currentUser['role'] ?? 'user') === 'admin')Global@else Notificaciones @endif</button>
            </div>

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

            <div id="broadcastView" class="chat-view">
                <div class="chat-messages" id="broadcastMessages"></div>
                <div class="chat-input-area" @if (($currentUser['role'] ?? 'user') !== 'admin') style="display:none;" @endif>
                    <input type="text" id="broadcastInput" placeholder="Mensaje para todos..." onkeypress="if(event.key==='Enter') sendBroadcast();">
                    <button type="button" onclick="sendBroadcast()" class="chat-send-btn">Enviar</button>
                </div>
            </div>
        </div>

        <div class="container-main">
            <div class="container-toolbar">
                <button type="button" class="container-load-btn" onclick="loadInIframe(true)">Recargar Contenedor</button>
            </div>
            <iframe id="viewer"></iframe>
        </div>
    </div>
    <footer>Codename Virthub v0.7b</footer>
    <script>
        const currentUserName = @json($currentUser['username'] ?? 'guest');
        const chatNotificationSoundUrl = @json(asset('sounds/chat-notificacion.mp3'));
        const chatNotificationAudio = new Audio(chatNotificationSoundUrl);
        chatNotificationAudio.preload = 'auto';

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
            chatNotificationAudio.currentTime = 0;
            chatNotificationAudio.volume = 0.75;
            chatNotificationAudio.play().catch(() => {});
        }

        function unlockChatNotificationAudio() {
            chatNotificationAudio.play().then(() => {
                chatNotificationAudio.pause();
                chatNotificationAudio.currentTime = 0;
            }).catch(() => {});
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

                const body = document.createElement('p');
                const messageText = message.message || '';

                if (messageLabelPrefix) {
                    const strong = document.createElement('strong');
                    strong.textContent = messageLabelPrefix;
                    body.appendChild(strong);
                    body.appendChild(document.createTextNode(' ' + messageText));
                } else {
                    body.textContent = messageText;
                }

                const meta = document.createElement('small');
                const sender = message.from || '';
                const timeLabel = safeTimeLabel(message.created_at);
                meta.textContent = timeLabel ? `${sender}${sender ? ' • ' : ''}${timeLabel}` : sender;

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
            document.querySelectorAll('.chat-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.chat-view').forEach(view => view.classList.remove('active'));

            const tabButton = document.querySelector(`[data-tab="${tab}"]`);
            const tabView = document.getElementById(tab + 'View');

            if (tabButton) tabButton.classList.add('active');
            if (tabView) tabView.classList.add('active');

            if (tab === 'users') {
                await loadUsersList();
            }

            if (tab === 'broadcast') {
                await loadBroadcastMessages();
            }
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
                    list.innerHTML = '<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">No hay otros usuarios conectados.</p>';
                    return;
                }

                list.innerHTML = '';
                otherUsers.forEach(user => {
                    const item = document.createElement('div');
                    item.className = 'chat-user-item';
                    item.addEventListener('click', () => selectChatUser(user.username));

                    const name = document.createElement('span');
                    name.textContent = user.username;

                    const badge = document.createElement('span');
                    badge.className = 'chat-user-badge';
                    badge.textContent = user.role || 'user';

                    item.appendChild(name);
                    item.appendChild(badge);
                    list.appendChild(item);
                });
            } catch (error) {
                list.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        async function refreshConversationSilently(username, shouldNotify = false) {
            const messages = await loadConversationMessages(username);
            const previousCount = chatSnapshots.conversations[username] || 0;

            chatSnapshots.conversations[username] = messages.length;

            if (shouldNotify && messages.length > previousCount) {
                const newMessages = messages.slice(previousCount);
                const incoming = newMessages.filter(message => (message.from || '') !== getUserKey());

                incoming.forEach(message => {
                    showChatNotification(`Nuevo mensaje de ${message.from || username}`, message.message || 'Tienes un nuevo mensaje.');
                });
            }

            if (currentChatUser === username) {
                const messagesDiv = document.getElementById('chatMessages');
                renderChatMessages(messagesDiv, messages, `Chat con ${username}`, getUserKey());
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
                renderChatMessages(messagesDiv, messages, `Chat con ${username}`, getUserKey());
            } catch (error) {
                messagesDiv.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        async function sendChatMessage() {
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
                renderChatMessages(messagesDiv, messages, `Chat con ${currentChatUser}`, getUserKey());
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
                renderChatMessages(messagesDiv, messages, 'Sin notificaciones aún.', getUserKey(), '[ANUNCIO]');

                chatSnapshots.broadcast = messages.length;
            } catch (error) {
                messagesDiv.innerHTML = `<p style="text-align: center; color: var(--vh-text-soft); font-size: 12px; padding: 20px 10px;">${error.message}</p>`;
            }
        }

        async function sendBroadcast() {
            const input = document.getElementById('broadcastInput');
            if (!input || !input.value.trim()) return;

            const messageText = input.value.trim();

            try {
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
            }
        }

        function startChatPolling() {
            setInterval(async () => {
                try {
                    if (chatContacts.length === 0) {
                        await loadUsersList();
                    }

                    for (const contact of chatContacts) {
                        await refreshConversationSilently(contact.username, true);
                    }

                    const response = await apiFetch('/chat/broadcast');
                    const payload = await response.json();

                    if (response.ok) {
                        const messages = payload.messages || [];
                        const previousCount = chatSnapshots.broadcast || 0;

                        if (messages.length > previousCount) {
                            const newMessages = messages.slice(previousCount);
                            newMessages.forEach(message => {
                                if ((message.from || '') !== getUserKey()) {
                                    showChatNotification(`Broadcast de ${message.from || 'Sistema'}`, message.message || 'Nuevo anuncio disponible.');
                                }
                            });

                            chatSnapshots.broadcast = messages.length;

                            if (document.getElementById('broadcastView')?.classList.contains('active')) {
                                renderChatMessages(document.getElementById('broadcastMessages'), messages, 'Sin notificaciones aún.', getUserKey(), '[ANUNCIO]');
                            }
                        }
                    }
                } catch (error) {
                    // Silenciar errores temporales de polling.
                }
            }, 15000);
        }

        window.addEventListener('DOMContentLoaded', async () => {
            applySidebarState();
            applyThemeState();
            loadInIframe();
            syncChatLayoutState();
            startGuestCountdown();
            unlockChatNotificationAudio();
            await loadBroadcastMessages();
            await loadUsersList();
            startChatPolling();
        });
    </script>
</body>
</html>