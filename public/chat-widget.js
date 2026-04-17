(function () {
    if (typeof window === 'undefined') return;

    const chatPanel = document.getElementById('chatPanel');
    const chatToggle = document.getElementById('chatToggle');
    const chatClose = document.getElementById('chatCloseBtn');

    if (!chatPanel || !chatToggle) return;

    const currentUserName = window.VIRTHUB_CHAT_USER || 'guest';
    const chatNotificationSoundUrl = window.VIRTHUB_CHAT_SOUND || '/sounds/chat-notificacion.mp3';
    const currentUserProfile = window.VIRTHUB_CHAT_CURRENT_PROFILE || { username: currentUserName, profile_image_path: null, is_active: true };
    const chatNotificationAudio = new Audio(chatNotificationSoundUrl);
    chatNotificationAudio.preload = 'auto';

    let currentChatUser = null;
    let chatContacts = [];
    const userProfiles = {};
    const snapshots = { conversations: {}, broadcast: 0 };

    userProfiles[currentUserName] = Object.assign({}, currentUserProfile, { username: currentUserName });

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

        const t = document.createElement('span');
        t.className = 'chat-toast-title';
        t.textContent = title;

        const b = document.createElement('span');
        b.className = 'chat-toast-body';
        b.textContent = body;

        toast.appendChild(t);
        toast.appendChild(b);
        stack.appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
        }, 3200);

        setTimeout(() => toast.remove(), 3600);
    }

    function playChatNotificationSound() {
        chatNotificationAudio.currentTime = 0;
        chatNotificationAudio.volume = 0.75;
        chatNotificationAudio.play().catch(() => {});
    }

    function unlockAudio() {
        chatNotificationAudio.play().then(() => {
            chatNotificationAudio.pause();
            chatNotificationAudio.currentTime = 0;
        }).catch(() => {});
    }

    function showNotification(title, body) {
        playChatNotificationSound();

        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body, silent: true });
            return;
        }

        showChatToast(title, body);
    }

    function requestPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            Notification.requestPermission().catch(() => {});
        }
    }

    async function sendPresenceHeartbeat() {
        try {
            await apiFetch('/chat/presence', {
                method: 'POST',
                body: JSON.stringify({ ping_at: Date.now() })
            });
        } catch (error) {
            // Ignore temporary heartbeat failures.
        }
    }

    function safeTimeLabel(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    }

    function userInitial(username) {
        const value = String(username || 'U').trim();
        return (value.charAt(0) || 'U').toUpperCase();
    }

    function profileFor(username) {
        if (username === currentUserName) {
            return userProfiles[currentUserName] || currentUserProfile;
        }

        return userProfiles[username] || { username, profile_image_path: null, is_active: false };
    }

    function buildAvatarNode(profile, className) {
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

    function renderMessages(container, messages, emptyText, ownUser, prefix) {
        if (!container) return;
        container.innerHTML = '';

        if (!messages || messages.length === 0) {
            const p = document.createElement('p');
            p.style.textAlign = 'center';
            p.style.color = 'var(--vh-text-soft)';
            p.style.fontSize = '12px';
            p.style.padding = '20px 10px';
            p.textContent = emptyText;
            container.appendChild(p);
            return;
        }

        messages.forEach(message => {
            const row = document.createElement('div');
            row.className = 'chat-message' + ((message.from || '') === ownUser ? ' own' : '');

            const header = document.createElement('div');
            header.className = 'chat-message-header';

            const sender = message.from || '';
            const senderProfile = profileFor(sender);
            header.appendChild(buildAvatarNode(senderProfile, 'chat-message-avatar'));

            const body = document.createElement('p');
            if (prefix) {
                const strong = document.createElement('strong');
                strong.textContent = prefix;
                body.appendChild(strong);
                body.appendChild(document.createTextNode(' ' + (message.message || '')));
            } else {
                body.textContent = message.message || '';
            }

            const meta = document.createElement('small');
            const time = safeTimeLabel(message.created_at);
            meta.textContent = time ? sender + (sender ? ' • ' : '') + time : sender;

            row.appendChild(header);
            row.appendChild(body);
            row.appendChild(meta);
            container.appendChild(row);
        });

        container.scrollTop = container.scrollHeight;
    }

    async function loadUsersList() {
        const list = document.getElementById('chatUsersList');
        if (!list) return;

        list.innerHTML = '<p style="text-align:center;color:var(--vh-text-soft);font-size:12px;padding:20px 10px;">Cargando usuarios...</p>';

        try {
            const res = await apiFetch('/chat/users');
            const payload = await res.json();
            if (!res.ok) throw new Error(payload.error || 'No se pudieron cargar los usuarios');

            chatContacts = (payload.users || []).filter(u => u.username !== currentUserName);

            chatContacts.forEach(user => {
                const accountActive = user.account_active !== undefined ? !!user.account_active : !!user.is_active;

                userProfiles[user.username] = {
                    username: user.username,
                    profile_image_path: user.profile_image_path || null,
                    profile_frame_color: user.profile_frame_color || '#6ea8ff',
                    is_active: accountActive,
                    presence_status: user.presence_status || (accountActive ? 'online' : 'offline'),
                    role: user.role || 'user'
                };
            });

            if (chatContacts.length === 0) {
                list.innerHTML = '<p style="text-align:center;color:var(--vh-text-soft);font-size:12px;padding:20px 10px;">No hay otros usuarios conectados.</p>';
                return;
            }

            list.innerHTML = '';
            chatContacts.forEach(user => {
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

                const profile = userProfiles[user.username] || { username: user.username, profile_image_path: null, is_active: false };
                main.appendChild(buildAvatarNode(profile, 'chat-user-avatar'));

                const name = document.createElement('span');
                name.className = 'chat-user-name';
                name.textContent = user.username;
                main.appendChild(name);

                const status = document.createElement('span');
                status.className = 'chat-user-status ' + (isOnline ? 'active' : 'inactive');
                status.title = isOnline ? 'Conectado recientemente' : 'Desconectado';
                main.appendChild(status);

                const right = document.createElement('div');
                right.style.display = 'flex';
                right.style.alignItems = 'center';
                right.style.gap = '6px';

                const badge = document.createElement('span');
                badge.className = 'chat-user-badge';
                badge.textContent = user.role || 'user';
                right.appendChild(badge);

                item.appendChild(main);
                item.appendChild(right);
                list.appendChild(item);
            });
        } catch (error) {
            list.innerHTML = '<p style="text-align:center;color:var(--vh-text-soft);font-size:12px;padding:20px 10px;">' + error.message + '</p>';
        }
    }

    async function loadConversationMessages(username) {
        const res = await apiFetch('/chat/conversation/' + encodeURIComponent(username));
        const payload = await res.json();
        if (!res.ok) throw new Error(payload.error || 'No se pudo cargar la conversación');
        return payload.messages || [];
    }

    async function refreshConversationSilently(username, notify) {
        const messages = await loadConversationMessages(username);
        const prev = snapshots.conversations[username] || 0;
        snapshots.conversations[username] = messages.length;

        if (notify && messages.length > prev) {
            messages.slice(prev).filter(m => (m.from || '') !== currentUserName).forEach(m => {
                showNotification('Nuevo mensaje de ' + (m.from || username), m.message || 'Tienes un nuevo mensaje.');
            });
        }

        if (currentChatUser === username) {
            renderMessages(document.getElementById('chatMessages'), messages, 'Chat con ' + username, currentUserName, '');
        }

        return messages;
    }

    async function loadBroadcastMessages() {
        const box = document.getElementById('broadcastMessages');
        if (!box) return;

        box.innerHTML = '<p style="text-align:center;color:var(--vh-text-soft);font-size:12px;padding:20px 10px;">Cargando mensajes...</p>';

        try {
            const res = await apiFetch('/chat/broadcast');
            const payload = await res.json();
            if (!res.ok) throw new Error(payload.error || 'No se pudieron cargar las notificaciones');

            const messages = payload.messages || [];
            renderMessages(box, messages, 'No hay anuncios aún.', currentUserName, '[ANUNCIO]');
            snapshots.broadcast = messages.length;
        } catch (error) {
            box.innerHTML = '<p style="text-align:center;color:var(--vh-text-soft);font-size:12px;padding:20px 10px;">' + error.message + '</p>';
        }
    }

    async function selectChatUser(username) {
        currentChatUser = username;
        await switchChatTab('messages');

        const box = document.getElementById('chatMessages');
        if (!box) return;
        box.innerHTML = '<p style="text-align:center;color:var(--vh-text-soft);font-size:12px;padding:20px 10px;">Cargando mensajes...</p>';

        try {
            const messages = await refreshConversationSilently(username, false);
            renderMessages(box, messages, 'Chat con ' + username, currentUserName, '');
        } catch (error) {
            box.innerHTML = '<p style="text-align:center;color:var(--vh-text-soft);font-size:12px;padding:20px 10px;">' + error.message + '</p>';
        }
    }

    async function sendChatMessage() {
        const input = document.getElementById('chatInput');
        if (!input || !input.value.trim() || !currentChatUser) {
            if (!currentChatUser) alert('Selecciona un usuario primero');
            return;
        }

        try {
            const res = await apiFetch('/chat/conversation/' + encodeURIComponent(currentChatUser), {
                method: 'POST',
                body: JSON.stringify({ message: input.value.trim() })
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload.error || 'No se pudo enviar el mensaje');

            const messages = await refreshConversationSilently(currentChatUser, false);
            snapshots.conversations[currentChatUser] = messages.length;
            renderMessages(document.getElementById('chatMessages'), messages, 'Chat con ' + currentChatUser, currentUserName, '');
            input.value = '';
            input.focus();
        } catch (error) {
            alert(error.message);
        }
    }

    async function sendBroadcast() {
        const input = document.getElementById('broadcastInput');
        if (!input || !input.value.trim()) return;

        try {
            const res = await apiFetch('/chat/broadcast', {
                method: 'POST',
                body: JSON.stringify({ message: input.value.trim() })
            });
            const payload = await res.json();
            if (!res.ok) throw new Error(payload.error || 'No se pudo publicar el anuncio');

            await loadBroadcastMessages();
            input.value = '';
            input.focus();
        } catch (error) {
            alert(error.message);
        }
    }

    async function switchChatTab(tab) {
        document.querySelectorAll('.chat-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.chat-view').forEach(view => view.classList.remove('active'));

        const btn = document.querySelector('[data-tab="' + tab + '"]');
        const view = document.getElementById(tab + 'View');
        if (btn) btn.classList.add('active');
        if (view) view.classList.add('active');

        if (tab === 'users') await loadUsersList();
        if (tab === 'broadcast') await loadBroadcastMessages();
    }

    function toggleChat() {
        chatPanel.classList.toggle('is-open');
        unlockAudio();
        if (chatPanel.classList.contains('is-open')) {
            requestPermission();
            sendPresenceHeartbeat();
        }
    }

    function startChatPolling() {
        let notificationsPrimed = false;

        setInterval(async () => {
            try {
                await sendPresenceHeartbeat();

                if (chatContacts.length === 0) await loadUsersList();

                const shouldNotify = notificationsPrimed;
                for (const c of chatContacts) {
                    await refreshConversationSilently(c.username, shouldNotify);
                }

                const res = await apiFetch('/chat/broadcast');
                const payload = await res.json();
                if (!res.ok) return;

                const messages = payload.messages || [];
                const prev = snapshots.broadcast || 0;
                if (messages.length > prev) {
                    if (shouldNotify) {
                        messages.slice(prev).forEach(m => {
                            if ((m.from || '') !== currentUserName) {
                                showNotification('Broadcast de ' + (m.from || 'Sistema'), m.message || 'Nuevo anuncio disponible.');
                            }
                        });
                    }

                    snapshots.broadcast = messages.length;
                    if (document.getElementById('broadcastView') && document.getElementById('broadcastView').classList.contains('active')) {
                        renderMessages(document.getElementById('broadcastMessages'), messages, 'No hay anuncios aún.', currentUserName, '[ANUNCIO]');
                    }
                }

                notificationsPrimed = true;
            } catch (error) {
                // ignore temporary polling failures
            }
        }, 15000);
    }

    window.switchChatTab = switchChatTab;
    window.sendChatMessage = sendChatMessage;
    window.sendBroadcast = sendBroadcast;
    window.toggleChat = toggleChat;

    if (chatClose) {
        chatClose.addEventListener('click', toggleChat);
    }
    chatToggle.addEventListener('click', toggleChat);

    window.addEventListener('DOMContentLoaded', async () => {
        unlockAudio();
        await sendPresenceHeartbeat();
        await loadBroadcastMessages();
        await loadUsersList();
        startChatPolling();
    });
})();
