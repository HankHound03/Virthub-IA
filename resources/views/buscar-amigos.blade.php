<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buscar amigos - VirtHub</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    <style>
        .friends-page { max-width: 900px; margin: 12px auto; padding: 14px; color: var(--vh-panel-text); }
        .friends-panel, .friend-result { background: var(--vh-surface); border: 1px solid var(--vh-border); padding: 14px; font-family: Monocraft Nerd Font, monospace; }
        .friends-panel h2 { margin-top: 0; color: var(--vh-text); }
        .friends-status { margin: 0 0 12px; padding: 10px; border: 1px solid var(--vh-border); }
        .friends-search { display: flex; gap: 8px; }
        .friends-search input { flex: 1; min-width: 0; padding: 10px; border: 1px solid var(--vh-border); background: var(--vh-button-bg); color: var(--vh-text); font: inherit; }
        .friends-search button { padding: 10px 14px; border: 1px solid var(--vh-border); background: var(--vh-button-bg); color: var(--vh-text); font: inherit; cursor: pointer; }
        .friend-results { display: grid; gap: 8px; margin-top: 14px; }
        .friend-result { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .friend-result strong, .friend-result span { display: block; }
        .friend-result strong { color: var(--vh-text); }
        .friend-result span { color: var(--vh-text-soft); font-size: 12px; margin-top: 4px; overflow-wrap: anywhere; }
        .friend-result a { color: var(--vh-text); border: 1px solid var(--vh-border); padding: 7px 10px; text-decoration: none; white-space: nowrap; }
        .friend-result form { margin: 0; }
        .friend-result button { border: 1px solid var(--vh-border); padding: 7px 10px; background: var(--vh-button-bg); color: var(--vh-text); font: inherit; cursor: pointer; }
        .friends-empty { color: var(--vh-text-soft); }
        @media (max-width: 560px) {
            .friends-page { margin: 8px 0; padding: 8px; }
            .friends-panel, .friend-result { padding: 10px; }
            .friends-search, .friend-result { flex-direction: column; align-items: stretch; }
            .friends-search button, .friend-result a, .friend-result button { width: 100%; box-sizing: border-box; text-align: center; }
        }
    </style>
</head>
<body>
    @include('partials.header', ['pageTitle' => 'Buscar amigos', 'currentUser' => $currentUser ?? null, 'currentPage' => 'buscar-amigos'])
    <main class="friends-page">
        <section class="friends-panel">
            <h2>Encuentra a tus amigos</h2>
            @if (session('error')) <p class="friends-status auth-error">{{ session('error') }}</p> @endif
            @if (session('success')) <p class="friends-status auth-success">{{ session('success') }}</p> @endif

            @if (count($pendingRequests) > 0)
                <h3>Solicitudes recibidas</h3>
                <div class="friend-results">
                    @foreach ($pendingRequests as $request)
                        <article class="friend-result">
                            <div>
                                <strong>{{ $request['sender']['name'] ?? $request['from'] }}</strong>
                                <span>Username: @{{ $request['from'] }}</span>
                            </div>
                            <div>
                                <form method="POST" action="{{ url('/amistad/responder') }}">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $request['id'] }}">
                                    <button type="submit" name="status" value="accepted">Aceptar</button>
                                    <button type="submit" name="status" value="declined">Rechazar</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <form class="friends-search" method="GET" action="{{ url('/buscar-amigos') }}">
                <input type="search" name="q" value="{{ $query }}" maxlength="100" placeholder="Nombre, ID de usuario o username" autofocus>
                <button type="submit">Buscar</button>
            </form>
            @if ($query !== '')
                @if (count($results) > 0)
                    <div class="friend-results">
                        @foreach ($results as $user)
                            <article class="friend-result">
                                <div>
                                    <strong>{{ $user['name'] ?: $user['username'] }}</strong>
                                    <span>ID: {{ $user['id'] }}</span>
                                    <span>Username: {{ '@' . $user['username'] }}</span>
                                </div>
                                <div>
                                    <a href="{{ url('/perfil/' . rawurlencode($user['username'])) }}">Ver perfil</a>
                                    @php($friendship = $user['friendship'] ?? null)
                                    @if (($user['username'] ?? '') === ($currentUser['username'] ?? ''))
                                        <span>Tu perfil</span>
                                    @elseif (($friendship['status'] ?? '') === 'accepted')
                                        <span>Amigos</span>
                                    @elseif (($friendship['from'] ?? '') === ($currentUser['username'] ?? '') && ($friendship['status'] ?? '') === 'pending')
                                        <span>Solicitud enviada</span>
                                    @elseif (($friendship['to'] ?? '') === ($currentUser['username'] ?? '') && ($friendship['status'] ?? '') === 'pending')
                                        <span>Revisa tus solicitudes</span>
                                    @else
                                        <form method="POST" action="{{ url('/amistad/solicitud') }}">
                                            @csrf
                                            <input type="hidden" name="username" value="{{ $user['username'] }}">
                                            <button type="submit">Enviar solicitud</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="friends-empty">No encontramos usuarios con ese criterio.</p>
                @endif
            @else
                <p class="friends-empty">Busca por cualquiera de los tres datos públicos del perfil.</p>
            @endif
        </section>
    </main>
    <footer>Virthub 1.0</footer>
    <script>
        function toggleTheme() { document.body.classList.toggle('dark-mode'); }
        function toggleProfileMenu(event) { event?.stopPropagation(); document.querySelector('.toggleable-profile-menu')?.classList.toggle('is-open'); }
    </script>
</body>
</html>
