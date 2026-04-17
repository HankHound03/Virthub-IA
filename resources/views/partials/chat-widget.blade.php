@if (!empty($currentUser))
    @php
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
        </div>

        <div class="chat-panel" id="chatPanel">
            <div class="chat-header">
                <h3>Chat</h3>
                <button type="button" class="chat-close" id="chatCloseBtn">×</button>
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
    </div>

    <script>
        window.VIRTHUB_CHAT_USER = @json($currentUser['username'] ?? 'guest');
        window.VIRTHUB_CHAT_SOUND = @json(asset('sounds/chat-notificacion.mp3'));
        window.VIRTHUB_CHAT_CURRENT_PROFILE = @json($chatCurrentProfile);
    </script>
    <script src="{{ asset('chat-widget.js') }}?v={{ filemtime(public_path('chat-widget.js')) }}"></script>
@endif
