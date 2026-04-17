<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Foro VirtHub</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    <style>
        .forum-shell {
            margin: 5px;
            padding: 14px;
            background-color: var(--vh-surface-strong);
            border: 1px solid var(--vh-border);
            backdrop-filter: blur(10px);
        }

        .forum-layout {
            display: grid;
            grid-template-columns: minmax(260px, 340px) 1fr;
            gap: 12px;
            align-items: start;
        }

        .forum-card {
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            padding: 12px;
            color: var(--vh-panel-text);
            font-family: Monocraft Nerd Font, monospace;
        }

        .forum-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--vh-text);
        }

        .forum-form label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            color: var(--vh-text-soft);
        }

        .forum-form input,
        .forum-form textarea {
            width: 100%;
            box-sizing: border-box;
            background-color: var(--vh-button-bg);
            border: 1px solid var(--vh-border);
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
            font-size: 13px;
            padding: 8px;
            margin-top: 4px;
        }

        .forum-form textarea {
            resize: vertical;
            min-height: 140px;
        }

        .forum-form input[type="file"] {
            padding: 6px;
            cursor: pointer;
        }

        .forum-form button {
            margin-top: 6px;
            padding: 8px 12px;
            background-color: var(--vh-button-bg);
            border: 1px solid var(--vh-border);
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
            cursor: pointer;
        }

        .forum-form button:hover {
            background-color: var(--vh-button-hover);
        }

        .forum-posts {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .forum-post {
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            padding: 12px;
            color: var(--vh-panel-text);
            font-family: Monocraft Nerd Font, monospace;
        }

        .forum-post h4 {
            margin: 0 0 6px 0;
            color: var(--vh-text);
            font-size: 15px;
        }

        .forum-post-meta {
            display: block;
            margin-bottom: 8px;
            color: var(--vh-text-soft);
            font-size: 12px;
        }

        .forum-post-image {
            width: 100%;
            max-height: 460px;
            object-fit: contain;
            border: 1px solid var(--vh-border);
            margin: 8px 0;
            background-color: rgba(0, 0, 0, 0.2);
        }

        .forum-post-actions {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .forum-post-menu {
            position: relative;
            margin-left: auto;
        }

        .forum-post-menu > summary {
            list-style: none;
            border: 1px solid var(--vh-border);
            background-color: var(--vh-button-bg);
            color: var(--vh-text);
            cursor: pointer;
            padding: 4px 10px;
            font-family: Monocraft Nerd Font, monospace;
            font-size: 15px;
        }

        .forum-post-menu > summary::-webkit-details-marker {
            display: none;
        }

        .forum-post-menu-panel {
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            min-width: 190px;
            padding: 8px;
            border: 1px solid var(--vh-border);
            background-color: var(--vh-surface-strong);
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.25);
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .reaction-form,
        .delete-form {
            margin: 0;
            display: inline-flex;
        }

        .reaction-btn,
        .delete-btn {
            font-family: Monocraft Nerd Font, monospace;
            border: 1px solid var(--vh-border);
            background-color: var(--vh-button-bg);
            color: var(--vh-text);
            cursor: pointer;
            padding: 5px 9px;
            font-size: 12px;
        }

        .reaction-btn:hover,
        .delete-btn:hover {
            background-color: var(--vh-button-hover);
        }

        .delete-btn {
            border-color: rgba(255, 120, 120, 0.45);
        }

        .report-btn {
            border-color: rgba(255, 206, 100, 0.45);
        }

        .comment-box {
            margin-top: 12px;
            border-top: 1px solid var(--vh-border);
            padding-top: 10px;
        }

        .comment-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 10px;
        }

        .comment-item {
            border: 1px solid var(--vh-border);
            background-color: rgba(0, 0, 0, 0.10);
            padding: 8px;
        }

        .comment-meta {
            display: block;
            margin-bottom: 4px;
            color: var(--vh-text-soft);
            font-size: 11px;
        }

        .comment-form {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }

        .comment-form textarea {
            flex: 1;
            min-height: 64px;
            max-height: 160px;
            resize: vertical;
            background-color: var(--vh-button-bg);
            border: 1px solid var(--vh-border);
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
            font-size: 12px;
            padding: 8px;
        }

        .forum-empty {
            text-align: center;
            color: var(--vh-text-soft);
            font-family: Monocraft Nerd Font, monospace;
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            padding: 22px;
        }

        @media (max-width: 900px) {
            .forum-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-controls">
            <div class="toggleable-sidebar" onclick="toggleMenu(event)" aria-label="Abrir menu" title="Menu">
                <span class="menu-icon" aria-hidden="true"></span>
                <div class="sidebar" onclick="event.stopPropagation()">
                    <button type="button" onclick="location.href='{{ url('/') }}'">Home</button>
                    @if (!empty($currentUser))
                        <button type="button" onclick="location.href='{{ url('/contenedor') }}'">Contenedor</button>
                    @endif
                    @if (($currentUser['role'] ?? 'user') === 'admin')
                        <button type="button" onclick="location.href='{{ url('/admin/users') }}'">Panel Admin</button>
                    @endif
                </div>
            </div>
            <div class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Cambiar tema" aria-label="Cambiar tema">
                <span class="theme-icon" aria-hidden="true"></span>
            </div>
        </div>
        <h1>Foro VirtHub</h1>
    </header>

    @include('partials.chat-widget')

    <div class="forum-shell">
        @if (session('error'))
            <p class="auth-message auth-error">{{ session('error') }}</p>
        @endif

        @if (session('success'))
            <p class="auth-message auth-success">{{ session('success') }}</p>
        @endif

        <div class="forum-layout">
            <section class="forum-card">
                <h3>Publicar</h3>

                @if ($canPost)
                    <form class="forum-form" method="POST" action="{{ url('/foro') }}" enctype="multipart/form-data">
                        @csrf
                        <label for="forumTitle">Titulo (opcional)
                            <input type="text" id="forumTitle" name="title" maxlength="120" placeholder="Tema del post...">
                        </label>
                        <label for="forumContent">Contenido
                            <textarea id="forumContent" name="content" maxlength="5000" required placeholder="Comparte tu idea o pregunta..."></textarea>
                        </label>
                        <label for="forumImage">Foto (opcional)
                            <input type="file" id="forumImage" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
                        </label>
                        <button type="submit">Publicar</button>
                    </form>
                @else
                    <p class="auth-message auth-error">Solo usuarios registrados pueden publicar. Invitados y visitantes pueden leer el contenido del foro.</p>
                @endif
            </section>

            <section class="forum-posts">
                @forelse ($posts as $post)
                    <article class="forum-post">
                        @php
                            $reactionData = is_array($post['reactions'] ?? null) ? $post['reactions'] : [];
                            $reactionCounts = [
                                '👍' => count(is_array($reactionData['👍'] ?? null) ? $reactionData['👍'] : []),
                                '❤️' => count(is_array($reactionData['❤️'] ?? null) ? $reactionData['❤️'] : []),
                                '🔥' => count(is_array($reactionData['🔥'] ?? null) ? $reactionData['🔥'] : []),
                            ];
                            $isAdmin = (($currentUser['role'] ?? 'user') === 'admin');
                            $isOwner = (($post['author'] ?? '') === ($currentUser['username'] ?? ''));
                            $canDelete = !empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest') && ($isAdmin || $isOwner);
                            $canReact = !empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest');
                            $comments = is_array($post['comments'] ?? null) ? $post['comments'] : [];
                        @endphp
                        <h4>{{ $post['title'] ?: 'Publicacion sin titulo' }}</h4>
                        <span class="forum-post-meta">{{ $post['author'] ?? 'usuario' }} | {{ $post['created_at'] ?? '-' }}</span>
                        <div>{!! nl2br(e($post['content'] ?? '')) !!}</div>

                        @if (!empty($post['image_path']))
                            <img class="forum-post-image" src="{{ asset($post['image_path']) }}" alt="Imagen de publicacion de {{ $post['author'] ?? 'usuario' }}" loading="lazy">
                        @endif

                        <div class="forum-post-actions">
                            @if ($canReact)
                                <form class="reaction-form" method="POST" action="{{ url('/foro/' . ($post['id'] ?? '') . '/react') }}">
                                    @csrf
                                    <input type="hidden" name="reaction" value="like">
                                    <button type="submit" class="reaction-btn">👍 {{ $reactionCounts['👍'] }}</button>
                                </form>
                                <form class="reaction-form" method="POST" action="{{ url('/foro/' . ($post['id'] ?? '') . '/react') }}">
                                    @csrf
                                    <input type="hidden" name="reaction" value="love">
                                    <button type="submit" class="reaction-btn">❤️ {{ $reactionCounts['❤️'] }}</button>
                                </form>
                                <form class="reaction-form" method="POST" action="{{ url('/foro/' . ($post['id'] ?? '') . '/react') }}">
                                    @csrf
                                    <input type="hidden" name="reaction" value="fire">
                                    <button type="submit" class="reaction-btn">🔥 {{ $reactionCounts['🔥'] }}</button>
                                </form>
                            @else
                                <span class="forum-post-meta">Inicia sesion con usuario registrado para reaccionar.</span>
                            @endif

                            @if ($canReact)
                                <details class="forum-post-menu">
                                    <summary>⋯</summary>
                                    <div class="forum-post-menu-panel">
                                        <form class="reaction-form" method="POST" action="{{ url('/foro/' . ($post['id'] ?? '') . '/report') }}">
                                            @csrf
                                            <input type="hidden" name="reason" value="Contenido reportado por usuario">
                                            <button type="submit" class="reaction-btn report-btn">Reportar</button>
                                        </form>

                                        @if ($canDelete)
                                            <form class="delete-form" method="POST" action="{{ url('/foro/' . ($post['id'] ?? '') . '/delete') }}">
                                                @csrf
                                                <button type="submit" class="delete-btn">Borrar</button>
                                            </form>
                                        @endif
                                    </div>
                                </details>
                            @endif
                        </div>

                        <div class="comment-box">
                            <div class="comment-list">
                                @forelse ($comments as $comment)
                                    <div class="comment-item">
                                        <span class="comment-meta">{{ $comment['author'] ?? 'usuario' }} | {{ $comment['created_at'] ?? '-' }}</span>
                                        <div>{!! nl2br(e($comment['content'] ?? '')) !!}</div>
                                    </div>
                                @empty
                                    <span class="forum-post-meta">Sin comentarios todavía.</span>
                                @endforelse
                            </div>

                            @if ($canReact)
                                <form class="comment-form" method="POST" action="{{ url('/foro/' . ($post['id'] ?? '') . '/comment') }}">
                                    @csrf
                                    <textarea name="content" maxlength="1500" required placeholder="Escribe un comentario..."></textarea>
                                    <button type="submit" class="reaction-btn">Comentar</button>
                                </form>
                            @else
                                <span class="forum-post-meta">Inicia sesion con usuario registrado para comentar.</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="forum-empty">Todavia no hay publicaciones. Este es un buen momento para abrir el primer tema.</div>
                @endforelse
            </section>
        </div>
    </div>

    <footer>Codename Virthub v0.8</footer>

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

        window.addEventListener('DOMContentLoaded', applySidebarState);
        window.addEventListener('DOMContentLoaded', applyThemeState);
    </script>
</body>
</html>