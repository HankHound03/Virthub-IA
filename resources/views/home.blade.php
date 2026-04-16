<!DOCTYPE html>
<html lang="es">
<style></style>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VirtHub</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <h1>VirtHub</h1>
    </header>
    <div class= "toggleable-sidebar" onclick="toggleMenu()">
        ☰
    <div class="sidebar">
        <h2>Navegación</h2>
        <button onclick="location.href='/'">Home</button>
        <button onclick="location.href='/contenedor'">About</button>
        <button onclick="window.open('https://github.com/FrankMon03/Virthub-IA', '_blank')">GitHub Project</button>
    </div>
    </div>
    <div class="login-screen">
        <label for="Username">Usuario<p><input type="text" name="Usuario" id="Usuario"><p></label>
        <label for="Password">Contraseña<p><input type="password" name="Password" id="Password"></label>
        <p><button type="submit">Iniciar Sesion</button>
    </div>
    <footer>CodeName: VirtHub v0.2 IA Prototype</footer>
    <script>
        function toggleMenu() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.style.display = sidebar.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</body>
</html>