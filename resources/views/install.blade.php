<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalación de Virthub</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <style>
        :root {
            color-scheme: dark;
            --vh-body-bg: url('/pics/background.jpg');
            --vh-page-overlay: linear-gradient(180deg, rgba(7, 14, 24, 0.10), rgba(7, 14, 24, 0.20));
            --vh-surface: rgba(142, 166, 255, 0.2);
            --vh-surface-strong: rgba(117, 225, 160, 0.2);
            --vh-border: rgba(117, 225, 160, 0.22);
            --vh-icon: #24405c;
            --vh-text: #75e1a0;
            --vh-text-soft: #d8ffea;
            --vh-button-bg: rgba(117, 225, 160, 0.2);
            --vh-button-hover: rgba(117, 225, 160, 0.4);
            --vh-panel-text: #e8fff3;
            --vh-glass-blur: 16px;
            --vh-glass-sheen: linear-gradient(180deg, rgba(255, 255, 255, 0.34) 0%, rgba(255, 255, 255, 0.12) 34%, rgba(255, 255, 255, 0.02) 68%, rgba(255, 255, 255, 0) 100%);
            --vh-glass-inner-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.34), inset 0 -1px 0 rgba(255, 255, 255, 0.08);
            --vh-glass-drop-shadow: 0 14px 32px rgba(5, 20, 38, 0.34);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--vh-text);
            background-image: linear-gradient(180deg, rgba(7, 14, 24, 0.14), rgba(7, 14, 24, 0.3)), url('/pics/background.jpg?v=20260417-light');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image: var(--vh-page-overlay);
            z-index: 0;
        }

        .panel {
            position: relative;
            z-index: 1;
            width: min(100%, 760px);
            background-color: var(--vh-surface);
            border: 1px solid var(--vh-border);
            border-radius: 20px;
            overflow: hidden;
            backdrop-filter: blur(var(--vh-glass-blur)) saturate(142%);
            -webkit-backdrop-filter: blur(var(--vh-glass-blur)) saturate(142%);
            background-image: var(--vh-glass-sheen);
            box-shadow: var(--vh-glass-drop-shadow), var(--vh-glass-inner-shadow);
        }

        .hero {
            padding: 32px 32px 24px;
            background: rgba(255, 255, 255, 0.06);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(117, 225, 160, 0.18);
            color: var(--vh-text-soft);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        h1 { margin: 0 0 8px; font-size: 1.8rem; color: var(--vh-panel-text); }
        .muted { margin: 0; color: var(--vh-text-soft); line-height: 1.6; }
        .form { padding: 32px; }
        .status {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 10px;
            background: rgba(117, 225, 160, 0.18);
            color: var(--vh-panel-text);
            border: 1px solid var(--vh-border);
        }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: var(--vh-panel-text); }
        input {
            width: 100%;
            padding: 0.85rem 0.95rem;
            border-radius: 10px;
            border: 1px solid var(--vh-border);
            background-color: var(--vh-button-bg);
            color: var(--vh-panel-text);
            margin-bottom: 14px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        input::placeholder { color: rgba(232, 255, 243, 0.7); }
        input:focus {
            border-color: var(--vh-text);
            box-shadow: 0 0 0 3px rgba(117, 225, 160, 0.16);
            background-color: var(--vh-button-hover);
        }
        button {
            margin-top: 6px;
            padding: 0.9rem 1.1rem;
            border: 1px solid var(--vh-border);
            border-radius: 10px;
            background-color: var(--vh-button-bg);
            color: var(--vh-panel-text);
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        button:hover {
            background-color: var(--vh-button-hover);
            transform: translateY(-1px);
        }
        .footer-note { margin-top: 14px; color: var(--vh-text-soft); font-size: 0.95rem; }
        @media (max-width: 640px) {
            .hero, .form { padding: 24px; }
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="hero">
            <span class="eyebrow">Instalación</span>
            <h1>Configura el primer administrador</h1>
            <p class="muted">Completa este formulario para crear o actualizar la cuenta de administrador de una instalación nueva o limpia.</p>
        </div>

        <div class="form">
            @if ($statusMessage)
                <div class="status success">{{ $statusMessage }}</div>
            @endif

            <form method="POST" action="{{ url('/install') }}">
                @csrf
                <input type="hidden" name="key" value="{{ $installationKey }}">
                <label for="admin_username">Nombre de administrador</label>
                <input id="admin_username" name="admin_username" value="admin" required>

                <label for="admin_password">Contraseña</label>
                <input id="admin_password" name="admin_password" type="password" required>

                <label for="admin_password_confirmation">Confirmar contraseña</label>
                <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required>

                <button type="submit">Guardar configuración</button>
            </form>

            <p class="footer-note">Si prefieres, puedes dejar el valor por defecto y continuar con la aplicación principal.</p>
        </div>
    </div>
</body>
</html>
