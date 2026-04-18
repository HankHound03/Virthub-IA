@php
    $activePage = (string) ($currentPage ?? '');
    $hasUser = !empty($currentUser);
    $userRole = (string) ($currentUser['role'] ?? 'guest');

    $links = [
        ['key' => 'home', 'label' => 'Home', 'url' => url('/'), 'visible' => true],
        ['key' => 'foro', 'label' => 'Foro', 'url' => url('/foro'), 'visible' => true],
        ['key' => 'sugerencias', 'label' => 'Sugerencias', 'url' => url('/sugerencias'), 'visible' => true],
        ['key' => 'contenedor', 'label' => 'Contenedor', 'url' => url('/contenedor'), 'visible' => $hasUser],
        ['key' => 'admin', 'label' => 'Panel Admin', 'url' => url('/admin/users'), 'visible' => $userRole === 'admin'],
    ];
@endphp

@foreach ($links as $link)
    @if (!($link['visible'] ?? false))
        @continue
    @endif

    @if ($activePage === ($link['key'] ?? ''))
        <button type="button" class="menu-current" disabled aria-current="page">{{ $link['label'] }}</button>
    @else
        <button type="button" onclick="location.href='{{ $link['url'] }}'">{{ $link['label'] }}</button>
    @endif
@endforeach

<button type="button" onclick="window.open('https://github.com/FrankMon03/Virthub-IA', '_blank')">GitHub Project</button>
