<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido $Usuario$</title>
    <link rel="stylesheet" href="/container.css">
</head>
<body>
    <header>
        <h1>Bienvenido $Usuario$</h1>
    </header>
    <div class= "toggleable-sidebar" onclick="toggleMenu()">
        ☰
    <div class="sidebar">
        <h2>Navegación</h2>
        <button onclick="location.href='/'">Cerrar Sesion</button>
        <button onclick="loadInIframe()">Cargar Contenedor</button>
    </div>
    </div>
    <iframe id="viewer"></iframe>
    <footer>CodeName: VirtHub v0.2 IA Prototype</footer>
    <script>
        function toggleMenu() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.style.display = sidebar.style.display === 'block' ? 'none' : 'block';
        }
        function loadInIframe() {
            const iframe = document.getElementById('viewer');
            if (iframe) iframe.src = '/contenedor/launch';
        }
    </script>
</body>
</html>