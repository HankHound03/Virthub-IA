<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cambiar contrasena - VirtHub</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    <style>
        .password-change-shell {
            max-width: 520px;
            margin: 10px auto;
            padding: 18px;
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            border-radius: 14px;
            color: var(--vh-text);
            font-family: Monocraft Nerd Font, monospace;
        }

        .password-change-shell h2 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .password-change-shell p {
            margin: 0 0 14px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--vh-panel-text);
        }

        .password-change-shell label {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            color: var(--vh-text-soft);
        }

        .password-change-shell input {
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 12px;
            padding: 10px;
            border: 1px solid var(--vh-border);
            border-radius: 8px;
            background-color: var(--vh-button-bg);
            color: var(--vh-text);
            font: inherit;
        }

        .password-change-shell button {
            width: 100%;
            padding: 11px;
            border: 1px solid var(--vh-border);
            border-radius: 10px;
            background-color: var(--vh-button-bg);
            color: var(--vh-text);
            font: inherit;
            cursor: pointer;
        }

        .password-change-shell button:hover {
            background-color: var(--vh-button-hover);
        }

        .password-change-error {
            margin: 0 0 12px;
            padding: 9px 11px;
            border: 1px solid rgba(229, 90, 90, 0.45);
            border-radius: 8px;
            background-color: rgba(229, 90, 90, 0.14);
            color: #ffd7d7;
            font-size: 12px;
        }

        @media (max-width: 768px),
               (orientation: landscape) and (max-height: 560px) and (max-width: 1100px) {
            .password-change-shell {
                margin: 6px 0;
                padding: 14px;
            }
        }
    </style>
    @include('partials.tema-boot')
</head>
<body>
    @include('partials.header', [
        'pageTitle' => 'Cambiar contrasena',
        'currentUser' => $currentUser ?? null,
        'currentPage' => 'perfil'
    ])

    <main class="password-change-shell">
        <h2>Cambia tu contrasena</h2>

        <p>
            Tu cuenta se creo con una contrasena generada automaticamente, y esa
            contrasena la conoce quien creo la cuenta. Antes de continuar, elige
            una que solo sepas tu.
        </p>

        @if ($errors->any())
            <div class="password-change-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ url('/cambiar-password') }}">
            @csrf

            <label for="password">Contrasena nueva</label>
            <input type="password" id="password" name="password" required
                   minlength="8" autocomplete="new-password" autofocus>

            <label for="password_confirmation">Repite la contrasena</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required
                   minlength="8" autocomplete="new-password">

            <button type="submit">Guardar contrasena</button>
        </form>
    </main>

    <footer>{{ config('virthub.version') }}</footer>
</body>
</html>
