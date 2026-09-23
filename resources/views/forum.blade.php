<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Foro VirtHub</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
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
            display: block;
            align-items: start;
        }

        .forum-card {
            margin-bottom: 12px;
            width: 100%;
            box-sizing: border-box;
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

        .forum-compose-launcher {
            display: block;
            width: 100%;
            box-sizing: border-box;
            margin-top: 4px;
            padding: 8px;
            text-align: left;
            background-color: var(--vh-button-bg);
            border: 1px solid var(--vh-border);
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
            font-size: 13px;
            cursor: text;
        }

        .forum-compose-launcher:hover {
            background-color: var(--vh-button-hover);
        }

        .forum-compose-modal {
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            width: 100vw;
            height: 100vh;
            box-sizing: border-box;
            background: rgba(8, 17, 28, 0.78);
            backdrop-filter: blur(3px);
        }

        .forum-compose-modal.is-open {
            display: flex;
            animation: aero-compose-backdrop-in 0.24s ease-out both;
        }

        .forum-compose-card {
            width: min(680px, 100%);
            box-sizing: border-box;
            max-height: min(760px, 92vh);
            overflow: auto;
            padding: 16px;
            background: var(--vh-surface-strong);
            border: 1px solid var(--vh-border);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.38);
        }

        .forum-compose-modal.is-open .forum-compose-card {
            animation: aero-compose-card-in 0.38s cubic-bezier(0.22, 0.9, 0.2, 1) both;
        }

        @keyframes aero-compose-backdrop-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes aero-compose-card-in {
            from {
                opacity: 0;
                transform: translateY(16px) scale(0.96);
                filter: blur(4px);
            }
            70% {
                opacity: 1;
                transform: translateY(-2px) scale(1.005);
                filter: blur(0);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }

        .forum-compose-modal.is-closing {
            display: flex;
            animation: aero-compose-backdrop-out 0.2s ease-in both;
        }

        .forum-compose-modal.is-closing .forum-compose-card {
            animation: aero-compose-card-out 0.2s cubic-bezier(0.4, 0, 1, 1) both;
        }

        @keyframes aero-compose-backdrop-out {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        @keyframes aero-compose-card-out {
            from {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
            to {
                opacity: 0;
                transform: translateY(10px) scale(0.97);
                filter: blur(3px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .forum-compose-modal.is-open,
            .forum-compose-modal.is-open .forum-compose-card,
            .forum-compose-modal.is-closing,
            .forum-compose-modal.is-closing .forum-compose-card {
                animation: none;
            }
        }

        .forum-compose-head,
        .forum-compose-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .forum-compose-head h3 {
            margin: 0;
        }

        .forum-compose-close {
            margin: 0;
            padding: 4px 9px;
        }

        .forum-attachment-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 8px 0 12px;
        }

        .forum-attachment-tools button {
            margin: 0;
            padding: 7px 9px;
        }

        .forum-attachment-tools input[type="file"] {
            display: none;
        }

        .forum-attachment-status {
            margin: 0 0 8px;
            color: var(--vh-text-soft);
            font-size: 11px;
        }

        #forumPollBuilder[hidden] {
            display: none;
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

        .poll-builder {
            margin-top: 10px;
            border: 1px dashed var(--vh-border);
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.08);
        }

        .poll-builder h4 {
            margin: 0 0 8px 0;
            color: var(--vh-text);
            font-size: 13px;
        }

        .poll-builder-help {
            margin: 0 0 8px 0;
            font-size: 11px;
            color: var(--vh-text-soft);
        }

        .poll-options-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .poll-add-option {
            align-self: flex-start;
            margin-top: 2px !important;
            padding: 5px 8px !important;
            font-size: 11px;
        }

        .forum-poll {
            margin-top: 12px;
            border: 1px solid var(--vh-border);
            background-color: rgba(0, 0, 0, 0.11);
            padding: 10px;
        }

        .forum-poll-question {
            margin: 0 0 8px 0;
            color: var(--vh-text);
            font-size: 14px;
        }

        .forum-poll-meta {
            margin-bottom: 8px;
            font-size: 11px;
            color: var(--vh-text-soft);
        }

        .forum-poll-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .forum-poll-item {
            border: 1px solid var(--vh-border);
            padding: 8px;
            background-color: rgba(0, 0, 0, 0.12);
        }

        .forum-poll-vote-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .forum-poll-label {
            color: var(--vh-panel-text);
            font-size: 13px;
            line-height: 1.35;
        }

        .forum-poll-stats {
            color: var(--vh-text-soft);
            font-size: 11px;
        }

        .forum-poll-bar {
            height: 8px;
            border-radius: 999px;
            background-color: rgba(255, 255, 255, 0.08);
            overflow: hidden;
        }

        .forum-poll-bar-fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, rgba(109, 181, 255, 0.85), rgba(122, 228, 170, 0.92));
            transition: width 0.25s ease;
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

        .forum-report-modal {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            box-sizing: border-box;
            z-index: 2001;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-color: rgba(8, 17, 28, 0.68);
            backdrop-filter: blur(2px);
        }

        .forum-report-modal.is-open {
            display: flex;
        }

        .forum-report-card {
            width: min(560px, 100%);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-sizing: border-box;
            border: 1px solid var(--vh-border);
            background-color: var(--vh-surface-strong);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
            padding: 14px;
            color: var(--vh-panel-text);
            font-family: Monocraft Nerd Font, monospace;
        }

        .forum-report-card h3 {
            margin: 0 0 8px 0;
            color: var(--vh-text);
            font-size: 16px;
        }

        .forum-report-help {
            margin: 0 0 10px 0;
            color: var(--vh-text-soft);
            font-size: 12px;
            line-height: 1.4;
        }

        .forum-report-card textarea {
            width: 100%;
            box-sizing: border-box;
            min-height: 120px;
            resize: vertical;
            border: 1px solid var(--vh-border);
            background-color: var(--vh-button-bg);
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
            font-size: 12px;
            padding: 8px;
        }

        .forum-report-actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .forum-report-actions button {
            min-width: 120px;
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
            .forum-shell {
                margin: 3px;
                padding: 10px;
            }

            .forum-layout {
                grid-template-columns: 1fr;
            }

            .forum-card,
            .forum-post {
                padding: 10px;
            }

            .forum-post-image {
                max-height: 320px;
            }

            .forum-form button,
            .poll-builder .reaction-btn,
            .forum-poll .reaction-btn {
                width: 100%;
                text-align: center;
            }

            .forum-post-actions {
                gap: 6px;
            }

            .forum-post-menu {
                margin-left: 0;
            }

            .forum-post-menu-panel {
                right: auto;
                left: 0;
                min-width: 170px;
            }

            .comment-form {
                flex-direction: column;
            }

            .comment-form .reaction-btn {
                width: 100%;
            }

            .forum-poll-vote-row {
                align-items: flex-start;
                gap: 4px;
            }

            .forum-poll-label {
                width: 100%;
            }

            .forum-poll-stats {
                width: 100%;
            }
        }

        @media (max-width: 560px) {
            .forum-shell {
                margin: 0;
                padding: 8px;
            }

            .forum-card h3,
            .forum-post h4,
            .forum-poll-question {
                font-size: 14px;
            }

            .forum-form label,
            .forum-post-meta,
            .forum-poll-label {
                font-size: 12px;
            }

            .forum-form input,
            .forum-form textarea,
            .comment-form textarea,
            .reaction-btn,
            .delete-btn {
                font-size: 12px;
            }

            .poll-builder {
                padding: 8px;
            }

            .forum-compose-modal {
                padding: 8px;
            }

            .forum-compose-card {
                max-height: calc(100vh - 16px);
                padding: 12px;
            }

            .forum-compose-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .forum-compose-actions button,
            .poll-add-option {
                width: 100%;
            }

            .forum-report-modal {
                padding: 8px;
            }

            .forum-report-card {
                max-height: calc(100vh - 16px);
                padding: 12px;
            }

            .forum-poll {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    @include('partials.header', [
        'pageTitle' => 'Foro VirtHub',
        'currentUser' => $currentUser ?? null,
        'currentPage' => 'foro'
    ])

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
                    <button type="button" class="forum-compose-launcher" id="openForumComposer">¿Que quieres compartir?</button>
                    <div class="forum-compose-modal" id="forumComposeModal" aria-hidden="true">
                        <div class="forum-compose-card" role="dialog" aria-modal="true" aria-labelledby="forumComposeTitle" onclick="event.stopPropagation()">
                            <div class="forum-compose-head">
                                <h3 id="forumComposeTitle">Crear publicacion</h3>
                                <button type="button" class="forum-compose-close" id="closeForumComposer" aria-label="Cerrar">×</button>
                            </div>
                    <form class="forum-form" method="POST" action="{{ url('/foro') }}" enctype="multipart/form-data">
                        @csrf
                        <label for="forumTitle">Titulo (opcional)
                            <input type="text" id="forumTitle" name="title" maxlength="120" placeholder="Tema del post...">
                        </label>
                        <label for="forumContent">Contenido
                            <textarea id="forumContent" name="content" maxlength="5000" required placeholder="Comparte tu idea o pregunta..."></textarea>
                        </label>
                        <div class="forum-attachment-tools" aria-label="Agregar contenido">
                            <button type="button" data-file-trigger="forumPhotos">▣ Foto</button>
                            <button type="button" id="toggleForumPoll">▤ Encuesta</button>
                            <button type="button" data-file-trigger="forumVideos">▶ Video</button>
                            <button type="button" data-file-trigger="forumFiles">▤ Archivo</button>
                            <input type="file" id="forumPhotos" name="photos[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
                            <input type="file" id="forumVideos" name="videos[]" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" multiple>
                            <input type="file" id="forumFiles" name="files[]" multiple>
                        </div>
                        <p class="forum-attachment-status" id="forumAttachmentStatus">Puedes adjuntar archivos de hasta 5 GB cada uno.</p>

                        <div class="poll-builder" id="forumPollBuilder" hidden>
                            <h4>Encuesta (opcional)</h4>
                            <p class="poll-builder-help">Si agregas pregunta, debes completar al menos 2 opciones.</p>
                            <label for="pollQuestion">Pregunta
                                <input type="text" id="pollQuestion" name="poll_question" maxlength="180" value="{{ old('poll_question') }}" placeholder="Ejemplo: Que tema quieres ver la proxima semana?">
                            </label>
                            <div class="poll-options-list" id="pollOptionsList">
                                <label>Opcion 1
                                    <input type="text" name="poll_options[]" maxlength="120" placeholder="Primera opcion">
                                </label>
                                <label>Opcion 2
                                    <input type="text" name="poll_options[]" maxlength="120" placeholder="Segunda opcion">
                                </label>
                            </div>
                            <button type="button" class="poll-add-option" id="addPollOption">+ Agregar opcion</button>
                        </div>

                        <div class="forum-compose-actions">
                            <button type="button" class="forum-compose-close">Cancelar</button>
                            <button type="submit">Publicar</button>
                        </div>
                    </form>
                        </div>
                    </div>
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
                            $poll = is_array($post['poll'] ?? null) ? $post['poll'] : null;
                            $pollQuestion = trim((string) ($poll['question'] ?? ''));
                            $pollOptionsRaw = is_array($poll['options'] ?? null) ? $poll['options'] : [];
                            $pollOptions = [];
                            $totalPollVotes = 0;
                            $currentPollVoteId = null;
                            $currentPollUser = (string) ($currentUser['username'] ?? '');

                            foreach ($pollOptionsRaw as $option) {
                                $optionId = (string) ($option['id'] ?? '');
                                $optionLabel = trim((string) ($option['label'] ?? ''));
                                $optionVotes = is_array($option['votes'] ?? null) ? $option['votes'] : [];
                                $optionVotes = array_values(array_filter($optionVotes, function ($voteUser): bool {
                                    return is_string($voteUser) && trim($voteUser) !== '';
                                }));

                                if ($optionId === '' || $optionLabel === '') {
                                    continue;
                                }

                                if ($currentPollUser !== '' && in_array($currentPollUser, $optionVotes, true)) {
                                    $currentPollVoteId = $optionId;
                                }

                                $voteCount = count($optionVotes);
                                $totalPollVotes += $voteCount;

                                $pollOptions[] = [
                                    'id' => $optionId,
                                    'label' => $optionLabel,
                                    'votes' => $optionVotes,
                                    'count' => $voteCount,
                                ];
                            }

                            $hasPoll = $pollQuestion !== '' && count($pollOptions) >= 2;
                        @endphp
                        <h4>{{ $post['title'] ?: 'Publicacion sin titulo' }}</h4>
                        <span class="forum-post-meta"><a href="{{ url('/perfil/' . rawurlencode((string) ($post['author'] ?? 'usuario'))) }}">{{ $post['author'] ?? 'usuario' }}</a> | {{ $post['created_at'] ?? '-' }}</span>
                        <div>{!! nl2br(e($post['content'] ?? '')) !!}</div>

                        @if (!empty($post['image_path']))
                            <img class="forum-post-image" src="{{ asset($post['image_path']) }}" alt="Imagen de publicacion de {{ $post['author'] ?? 'usuario' }}" loading="lazy">
                        @endif

                        @if (!empty($post['attachments']) && is_array($post['attachments']))
                            @foreach ($post['attachments'] as $attachment)
                                @if (($attachment['type'] ?? '') === 'photo')
                                    <img class="forum-post-image" src="{{ asset($attachment['path']) }}" alt="Imagen adjunta" loading="lazy">
                                @elseif (($attachment['type'] ?? '') === 'video')
                                    <video class="forum-post-image" controls preload="metadata"><source src="{{ asset($attachment['path']) }}" type="{{ $attachment['mime'] ?? 'video/mp4' }}"></video>
                                @else
                                    <p><a href="{{ asset($attachment['path']) }}" target="_blank" rel="noopener">{{ $attachment['name'] ?? 'Abrir archivo adjunto' }}</a></p>
                                @endif
                            @endforeach
                        @endif

                        @if ($hasPoll)
                            <div class="forum-poll">
                                <h5 class="forum-poll-question">Encuesta: {{ $pollQuestion }}</h5>
                                <div class="forum-poll-meta">Total de votos: {{ $totalPollVotes }}</div>

                                <ul class="forum-poll-list">
                                    @foreach ($pollOptions as $pollOption)
                                        @php
                                            $optionCount = (int) ($pollOption['count'] ?? 0);
                                            $optionPercent = $totalPollVotes > 0 ? round(($optionCount / $totalPollVotes) * 100, 1) : 0;
                                            $isCurrentVote = (($pollOption['id'] ?? '') === $currentPollVoteId);
                                        @endphp
                                        <li class="forum-poll-item">
                                            <div class="forum-poll-vote-row">
                                                <span class="forum-poll-label">{{ $pollOption['label'] ?? '-' }}</span>
                                                <span class="forum-poll-stats">{{ $optionCount }} voto(s) | {{ $optionPercent }}%</span>
                                            </div>
                                            <div class="forum-poll-bar">
                                                <span class="forum-poll-bar-fill" style="width: {{ $optionPercent }}%"></span>
                                            </div>

                                            @if ($canReact)
                                                <form class="reaction-form" method="POST" action="{{ url('/foro/' . ($post['id'] ?? '') . '/poll-vote') }}" style="margin-top: 8px;">
                                                    @csrf
                                                    <input type="hidden" name="option_id" value="{{ $pollOption['id'] ?? '' }}">
                                                    <button type="submit" class="reaction-btn">{{ $isCurrentVote ? 'Tu voto' : 'Votar' }}</button>
                                                </form>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>

                                @if (!$canReact)
                                    <div class="forum-poll-meta" style="margin-top: 8px;">Inicia sesion con usuario registrado para votar en la encuesta.</div>
                                @endif
                            </div>
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
                                        <button
                                            type="button"
                                            class="reaction-btn report-btn js-open-report-modal"
                                            data-post-id="{{ $post['id'] ?? '' }}"
                                        >
                                            Reportar
                                        </button>

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

    <div class="forum-report-modal" id="forumReportModal" aria-hidden="true">
        <div class="forum-report-card" role="dialog" aria-modal="true" aria-labelledby="forumReportTitle" onclick="event.stopPropagation()">
            <h3 id="forumReportTitle">Reportar publicacion</h3>
            <p class="forum-report-help">Describe brevemente el motivo del reporte para moderacion (minimo 8 caracteres).</p>

            <form id="forumReportForm" method="POST" action="{{ url('/foro') }}">
                @csrf
                <label for="forumReportReason" class="forum-post-meta">Motivo</label>
                <textarea id="forumReportReason" name="reason" maxlength="280" minlength="8" required placeholder="Ejemplo: spam repetido, insultos, contenido fuera de reglas..."></textarea>

                <div class="forum-report-actions">
                    <button type="button" class="reaction-btn" id="forumReportCancel">Cancelar</button>
                    <button type="submit" class="reaction-btn report-btn">Enviar reporte</button>
                </div>
            </form>
        </div>
    </div>

    <footer>Virthub 1.0</footer>

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

        function buildReportAction(postId) {
            return '/foro/' + encodeURIComponent(postId) + '/report';
        }

        function openReportModal(postId) {
            const modal = document.getElementById('forumReportModal');
            const form = document.getElementById('forumReportForm');
            const textarea = document.getElementById('forumReportReason');
            if (!modal || !form || !textarea || !postId) return;

            form.setAttribute('action', buildReportAction(postId));
            textarea.value = '';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');

            window.setTimeout(() => textarea.focus(), 20);
        }

        function closeReportModal() {
            const modal = document.getElementById('forumReportModal');
            if (!modal) return;

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        function bindReportModalEvents() {
            document.querySelectorAll('.js-open-report-modal').forEach(button => {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    const postId = button.getAttribute('data-post-id') || '';
                    openReportModal(postId);
                });
            });

            const modal = document.getElementById('forumReportModal');
            const cancelBtn = document.getElementById('forumReportCancel');

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    closeReportModal();
                });
            }

            if (modal) {
                modal.addEventListener('click', function () {
                    closeReportModal();
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeReportModal();
                }
            });
        }

        function bindForumComposer() {
            const modal = document.getElementById('forumComposeModal');
            const openButton = document.getElementById('openForumComposer');
            const pollBuilder = document.getElementById('forumPollBuilder');
            const pollToggle = document.getElementById('toggleForumPoll');
            const pollOptionsList = document.getElementById('pollOptionsList');
            const addPollOption = document.getElementById('addPollOption');
            if (!modal || !openButton) return;

            document.body.appendChild(modal);

            const close = () => {
                if (!modal.classList.contains('is-open') || modal.classList.contains('is-closing')) return;

                modal.classList.remove('is-open');
                modal.classList.add('is-closing');
                modal.setAttribute('aria-hidden', 'true');
                window.setTimeout(() => modal.classList.remove('is-closing'), 220);
            };

            openButton.addEventListener('click', () => {
                modal.classList.remove('is-closing');
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.getElementById('forumContent')?.focus();
            });
            modal.querySelectorAll('.forum-compose-close').forEach(button => button.addEventListener('click', close));
            modal.addEventListener('click', close);
            pollToggle?.addEventListener('click', () => {
                pollBuilder.hidden = !pollBuilder.hidden;
            });
            addPollOption?.addEventListener('click', () => {
                if (!pollOptionsList || pollOptionsList.children.length >= 10) return;

                const optionNumber = pollOptionsList.children.length + 1;
                const label = document.createElement('label');
                label.textContent = 'Opcion ' + optionNumber;

                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'poll_options[]';
                input.maxLength = 120;
                input.placeholder = 'Nueva opcion';

                label.appendChild(input);
                pollOptionsList.appendChild(label);
                input.focus();
            });
            document.querySelectorAll('[data-file-trigger]').forEach(button => {
                button.addEventListener('click', () => document.getElementById(button.dataset.fileTrigger)?.click());
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') close();
            });
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

        window.addEventListener('DOMContentLoaded', applySidebarState);
        window.addEventListener('DOMContentLoaded', applyThemeState);
        window.addEventListener('DOMContentLoaded', bindReportModalEvents);
        window.addEventListener('DOMContentLoaded', bindForumComposer);
    </script>
</body>
</html>