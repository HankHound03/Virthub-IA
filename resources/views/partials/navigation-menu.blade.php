@php
    $activePage = (string) ($currentPage ?? '');
    $hasUser = !empty($currentUser);
    $userRole = (string) ($currentUser['role'] ?? 'guest');

    $links = [
        ['key' => 'home', 'label' => 'Inicio', 'url' => url('/'), 'visible' => true, 'icono' => 'casa'],
        ['key' => 'foro', 'label' => 'Foro', 'url' => url('/foro'), 'visible' => true],
        ['key' => 'sugerencias', 'label' => 'Sugerencias', 'url' => url('/sugerencias'), 'visible' => true],
        ['key' => 'contenedor', 'label' => 'Contenedor', 'url' => url('/contenedor'), 'visible' => $hasUser],
        ['key' => 'admin', 'label' => 'Admin', 'url' => url('/admin/users'), 'visible' => $userRole === 'admin'],
    ];
@endphp

<nav class="navbar">
    @foreach ($links as $link)
        @if (!($link['visible'] ?? false))
            @continue
        @endif

        @php($esCasa = ($link['icono'] ?? '') === 'casa')

        @if ($activePage === ($link['key'] ?? '') && !($link['external'] ?? false))
            <a href="{{ $link['url'] }}" class="navbar-link active" aria-current="page"
               @if ($esCasa) aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}" @endif>
                @if ($esCasa)
                    <span class="navbar-icono" aria-hidden="true"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7.3 8 2.2l6 5.1V14H2z"/><path d="M6.3 14V9.5h3.4V14"/></svg></span>
                    <span class="navbar-texto-oculto">{{ $link['label'] }}</span>
                @else
                    {{ $link['label'] }}
                @endif
            </a>
        @else
            <a href="{{ $link['url'] }}"
               class="navbar-link"
               @if ($esCasa) aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}" @endif
               @if ($link['external'] ?? false) target="_blank" rel="noopener noreferrer" @endif>
                @if ($esCasa)
                    <span class="navbar-icono" aria-hidden="true"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7.3 8 2.2l6 5.1V14H2z"/><path d="M6.3 14V9.5h3.4V14"/></svg></span>
                    <span class="navbar-texto-oculto">{{ $link['label'] }}</span>
                @else
                    {{ $link['label'] }}
                @endif
            </a>
        @endif
    @endforeach
</nav>

<style>
.navbar {
    display: flex;
    gap: 12px;
    padding: 0 20px;
    align-items: center;
    flex-wrap: wrap;
    background-color: transparent;
    backdrop-filter: none;
    border: none;
    box-shadow: none;
}

.navbar-link {
    padding: 8px 14px;
    border: 1px solid var(--vh-border);
    border-radius: 10px;
    background-color: var(--vh-button-bg);
    color: var(--vh-text);
    text-decoration: none;
    cursor: pointer;
    font-family: Monocraft Nerd Font, monospace;
    font-size: 13px;
    font-weight: 500;
    transition: background-color 0.25s ease, color 0.25s ease, transform 0.2s ease;
    display: inline-block;
    white-space: nowrap;
}

.navbar-link:hover {
    background-color: var(--vh-button-hover);
    color: var(--vh-panel-text);
    transform: translateY(-1px);
}

.navbar-link.active {
    background-color: var(--vh-button-hover);
    color: var(--vh-panel-text);
    border-color: rgba(117, 225, 160, 0.5);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
}

.navbar-link.active:hover {
    transform: translateY(-2px);
}


.navbar-icono {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 0;
}

/* Texto solo para lectores de pantalla: la casa se entiende sola a la vista. */
.navbar-texto-oculto {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    white-space: nowrap;
    border: 0;
}

.navbar-link[aria-label] {
    padding-left: 11px;
    padding-right: 11px;
}

@media (max-width: 768px) {
    .navbar {
        padding: 0 12px;
        gap: 8px;
    }

    .navbar-link {
        padding: 6px 12px;
        font-size: 12px;
    }
}
</style>