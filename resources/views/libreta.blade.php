<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Libreta · Virthub</title>
    <link rel="icon" type="image/png" href="{{ asset('pics/logovh.png') }}">
    <link rel="stylesheet" href="{{ asset('style.css') }}?v={{ filemtime(public_path('style.css')) }}">
    <link rel="stylesheet" href="{{ asset('container.css') }}?v={{ filemtime(public_path('container.css')) }}">
    @include('partials.tema-boot')
</head>

<body class="libreta-cuerpo">

@include('partials.header', [
    'currentUser' => $currentUser ?? null,
    'currentPage' => 'libreta',
])

@include('partials.libreta-cuerpo', ['flotante' => false])

</body>
</html>
