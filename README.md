# Virthub

Plataforma de acceso remoto a escritorios virtualizados (Webtop) pensada para
que personas con equipos de bajos recursos puedan trabajar desde un servidor
propio. Proyecto de hobby en desarrollo.

## Ejecutar con Docker

Requisitos: Docker Desktop con Compose habilitado.

1. Crea el archivo de entorno a partir de `.env.example` y cambia `APP_KEY`,
   `INSTALL_KEY`, `ADMIN_PASSWORD` y las contraseñas de la base de datos.

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

2. Construye y levanta la aplicación junto con MariaDB:

   ```bash
   docker compose up -d --build
   ```

La aplicación queda disponible en `http://localhost:8000`. `app` y `mariadb`
comparten la red virtual `virthub`; por eso la aplicación usa `DB_HOST=mariadb`
y no `localhost`.

3. Abre el instalador con la clave privada y crea la cuenta administradora:

   ```
   http://localhost:8000/install?key=TU_INSTALL_KEY
   ```

Para ver los registros:

```bash
docker compose logs -f app
```

Para detener los contenedores sin borrar los datos:

```bash
docker compose down
```

Los datos de MariaDB y el contenido de `storage` se conservan en volúmenes
Docker. Para eliminar también esos datos usa `docker compose down -v`.

## Desarrollo local

```bash
composer install
npm install
composer run dev
```

## Pruebas

```bash
composer test
# o directamente
php artisan test
```

## Estructura del código

La lógica vive en controladores, no en el archivo de rutas.

| Ruta | Responsabilidad |
| --- | --- |
| `routes/web.php` | Solo declara rutas, middleware y nombres |
| `app/Http/Controllers` | Un controlador por dominio (auth, foro, chat, admin…) |
| `app/Http/Middleware` | `ResolveActiveUser`, `EnsureRegisteredUser`, `EnsureAdmin` |
| `app/Support` | Servicios pequeños: contenedores, auditoría, Ollama, estado |
| `app/Services` | Persistencia (usuarios, foro, chat, amistades, perfil) |
| `config/virthub.php` | Toda la configuración del proyecto |

### Configuración

El proyecto **nunca** lee `env()` fuera de `config/`. Con
`php artisan config:cache` los valores de `env()` pasan a ser `null` en tiempo
de ejecución, así que toda la configuración propia vive en `config/virthub.php`
y se consume con `config('virthub.*')`.

### Almacén de usuarios

`App\Services\UserStore` es la interfaz que usa la aplicación.
`DatabaseUserStore` es la implementación de producción y `JsonUserStore` queda
como almacén heredado (y para el importador `importLegacyJson`). La suite de
pruebas cubre ambas.

## Seguridad

- Autenticación con contraseña y segundo factor TOTP opcional, con códigos de
  recuperación almacenados como hash.
- Bloqueo temporal tras intentos fallidos de inicio de sesión, más límite por IP.
- El acceso de invitado está limitado por IP porque reserva un escritorio
  compartido sin exigir credenciales.
- El instalador exige `INSTALL_KEY`. La vía sin clave solo se abre con
  `INSTALL_ALLOW_LOCALHOST=true` y desde el propio host.
- No se crean cuentas administradoras con contraseñas por defecto: si
  `ADMIN_PASSWORD` está vacía o es la del ejemplo, la creación se rechaza.
- Eventos de acceso y actividad en un canal de log dedicado (`security`).

## Objetivos

El detalle de los objetivos académicos del proyecto está en
[OBJETIVOS_PROYECTO.md](OBJETIVOS_PROYECTO.md).

## Autor

Estudiante de Ingeniería en Ciberseguridad. Proyecto de hobby para montar un
homelab con contenedores que mis amigos puedan usar como su propia computadora.

Firma: HankMon03 / FrankMon03
