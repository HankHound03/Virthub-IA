@if (!empty($currentUser))
    @php
        $isGuestChat = (($currentUser['role'] ?? 'guest') === 'guest');
        $chatCurrentProfile = [
            'username' => $currentUser['username'] ?? 'guest',
            'profile_image_path' => $currentUser['profile_image_path'] ?? null,
            'profile_frame_color' => $currentUser['profile_frame_color'] ?? '#6ea8ff',
            'is_active' => true,
        ];
    @endphp

    <style>
        .global-chat-widget {
            position: fixed;
            right: 18px;
            bottom: 18px;
            left: auto;
            top: auto;
            margin: 0;
            display: flex;
            flex-direction: row-reverse;
            align-items: flex-end;
            gap: 10px;
            z-index: 30;
            pointer-events: none;
        }

        .global-chat-widget .chat-toggle,
        .global-chat-widget .chat-panel {
            pointer-events: auto;
        }

        .global-chat-widget .chat-toggle {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            position: relative;
        }

        .global-chat-widget .chat-toggle .chat-icon {
            width: 22px;
            height: 22px;
        }

        .chat-notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 24px;
            height: 24px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ff6b6b, #ff4757);
            color: white;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--vh-bg);
            box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4);
            animation: badge-pulse 1.5s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes badge-pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.9;
            }
        }

        .chat-notification-badge.hidden {
            display: none;
        }

        .chat-notification-container {
            position: fixed;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 9999;
            pointer-events: none;
        }

        .chat-notification-container.home-position {
            bottom: 80px;
            right: 18px;
        }

        .chat-notification-container.container-position {
            top: 20px;
            right: 18px;
        }

        .chat-notification-item {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 360px;
            max-width: calc(100vw - 36px);
            padding: 14px 16px;
            border-radius: 8px;
            background: var(--vh-surface);
            border: 1px solid var(--vh-border);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            color: var(--vh-text);
            font-size: 13px;
            line-height: 1.4;
            pointer-events: auto;
            animation: slideInRight 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            backdrop-filter: blur(10px);
        }

        .chat-notification-item.removing {
            animation: slideOutRight 0.3s ease-in forwards;
        }

        .chat-notification-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .chat-notification-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .chat-notification-sender {
            font-weight: 600;
            font-size: 12px;
            color: var(--vh-text);
        }

        .chat-notification-message {
            font-size: 12px;
            color: var(--vh-text-soft);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-notification-close {
            width: 24px;
            height: 24px;
            border: none;
            background: transparent;
            color: var(--vh-text-soft);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
            padding: 0;
            transition: color 0.2s;
        }

        .chat-notification-close:hover {
            color: var(--vh-text);
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(400px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutRight {
            to {
                opacity: 0;
                transform: translateX(400px);
            }
        }

        @media (max-width: 768px) {
            .chat-notification-item {
                width: calc(100vw - 36px);
                max-width: 320px;
            }

            .chat-notification-container.home-position {
                bottom: 70px;
                right: 8px;
            }

            .chat-notification-container.container-position {
                top: 10px;
                right: 8px;
            }
        }

        .global-chat-widget .chat-panel {
            position: relative;
            height: min(520px, calc(100vh - 120px));
            min-height: 360px;
            max-height: calc(100vh - 120px);
        }

        .global-chat-widget .chat-panel.is-open {
            width: clamp(300px, 28vw, 390px);
            max-width: min(390px, calc(100vw - 86px));
        }

        @media (max-width: 768px) {
            .global-chat-widget {
                right: 8px;
                bottom: 8px;
            }

            .global-chat-widget .chat-toggle {
                width: 54px;
                height: 54px;
            }

            .global-chat-widget .chat-toggle .chat-icon {
                width: 24px;
                height: 24px;
            }

            .chat-notification-badge {
                top: -8px;
                right: -8px;
                min-width: 26px;
                height: 26px;
                font-size: 12px;
            }

            .global-chat-widget .chat-panel.is-open {
                width: min(92vw, 360px);
                height: min(460px, calc(100vh - 110px));
                min-height: 320px;
                max-height: calc(100vh - 110px);
            }
        }
    </style>

    <div class="global-chat-widget">
        <div class="chat-toggle" id="chatToggle" title="Abrir chat" aria-label="Abrir chat">
            <span class="chat-icon" aria-hidden="true"></span>
            <div class="chat-notification-badge hidden" id="chatNotificationBadge">0</div>
        </div>

        <audio id="chatNotificationAudio" preload="auto" style="display: none;">
            <source src="{{ asset('sounds/chat-notificacion.mp3') }}" type="audio/mpeg">
        </audio>

        <div class="chat-panel" id="chatPanel">
            <div class="chat-header">
                <h3>Chat</h3>
                <button type="button" class="chat-close" id="chatCloseBtn">×</button>
            </div>

            <div class="chat-tabs" id="chatTabs">
                @if (!$isGuestChat)
                <button type="button" class="chat-tab-btn active" onclick="switchChatTab('messages')" data-tab="messages">Mensajes</button>
                <button type="button" class="chat-tab-btn" onclick="switchChatTab('users')" data-tab="users">Usuarios</button>
                <button type="button" class="chat-tab-btn" onclick="switchChatTab('broadcast')" data-tab="broadcast">Anuncios</button>
                @else
                <button type="button" class="chat-tab-btn active" onclick="switchChatTab('broadcast')" data-tab="broadcast">Anuncios</button>
                @endif
            </div>

            @if (!$isGuestChat)
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
            @endif

            <div id="broadcastView" class="chat-view {{ $isGuestChat ? 'active' : '' }}">
                <div class="chat-messages" id="broadcastMessages"></div>
                <div class="chat-input-area" @if (($currentUser['role'] ?? 'user') !== 'admin') style="display:none;" @endif>
                    <input type="text" id="broadcastInput" placeholder="Mensaje para todos..." onkeypress="if(event.key==='Enter') sendBroadcast();">
                    <button type="button" onclick="sendBroadcast()" class="chat-send-btn">Enviar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.VIRTHUB_CHAT_USER = @json($currentUser['username'] ?? 'guest');
        window.VIRTHUB_CHAT_SOUND = @json(asset('sounds/chat-notificacion.mp3'));
        window.VIRTHUB_CHAT_CURRENT_PROFILE = @json($chatCurrentProfile);
        window.VIRTHUB_CHAT_MODE = @json($isGuestChat ? 'broadcast-only' : 'full');
        window.VIRTHUB_NOTIFICATION_POSITION = 'home';
    </script>
    <script src="{{ asset('chat-widget.js') }}?v={{ filemtime(public_path('chat-widget.js')) }}"></script>
@endif
