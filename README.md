# Virthub

Plataforma de acceso remoto a escritorios Linux pensada para que personas con
equipos de bajos recursos puedan trabajar desde un servidor propio: el alumno
solo necesita un navegador.

## Qué incluye

**Escritorios remotos**
- Un contenedor Webtop/Selkies por persona, entregado con un pase firmado y sin
  exponer los contenedores a internet.
- Reserva automática de escritorio, con una sola asignación activa por cuenta.
- Escritorio de invitados compartido: **un invitado a la vez**, y aislado del
  reparto de alumnos.

**Trabajo del aula**
- Foro con fotos, videos, archivos (hasta 5 GB por trozos, con reanudación),
  encuestas y reportes.
- Chat con presencia (en línea, ausente, desconectado), «escribiendo» y «visto».
- **Libreta** de notas en markdown, agrupadas por materia, con vista previa,
  buscador y una ventana flotante arrastrable dentro del contenedor.
- Perfil con foto, marco de color y publicaciones.

**Contenido seguro**
- Moderación de imágenes con NSFW-Sherlock en foro, adjuntos y **foto de perfil**.
- Antivirus ClamAV en toda subida, por socket y sin compartir archivos.

**Interfaz**
- 5 épocas de Windows y 30 esquemas de color, con adaptación a equipos modestos
  (menos animaciones y sin transparencias).
- Aurora animada de fondo en los temas Vista (triple clic en el logo).
- Todo funciona en móvil: barra, foro, chat, libreta y escritorio.

## Ejecutar con Docker

Requisitos: Docker Desktop con Compose habilitado.

La guía completa está en **[docker/DESPLIEGUE.md](docker/DESPLIEGUE.md)**: Caddy,
escritorios Webtop, túnel de Cloudflare, registro de escritorios, respaldos y
problemas típicos. Aquí va lo mínimo:

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

   Queda en `http://localhost:8000`. `app` y `mariadb` comparten la red virtual
   `virthub`; por eso `DB_HOST=mariadb` y no `localhost`.

3. Abre el instalador con la clave privada y crea la cuenta administradora:

   ```
   http://localhost:8000/install?key=TU_INSTALL_KEY
   ```

### Entornos

| Entorno | Compose | Puerto | Notas |
| --- | --- | --- | --- |
| Producción | `docker-compose.yml` | `127.0.0.1:8000` (app) y `127.0.0.1:8080` (Caddy) | Detrás del túnel de Cloudflare |
| Pruebas | `docker-compose.test.yml` | `8001` | `APP_DEBUG`, sin TLS. **Siempre con `-p virthub-test`** |

> Los dos compose usan los mismos nombres de servicio y de volumen a propósito.
> Si se olvida `-p virthub-test`, Compose trata el entorno de pruebas como si
> fuera producción y puede recrear sus contenedores y migrar su base.

### Respaldos

```powershell
# respaldar produccion (base + datos + adjuntos)
.\docker\respaldar-datos.ps1 -Proyecto virthub

# guardarlo en otro disco y no en el mismo
.\docker\respaldar-datos.ps1 -Proyecto virthub -Destino D:\respaldos

# restaurar el ultimo respaldo
.\docker\respaldar-datos.ps1 -Proyecto virthub -Restaurar
```

El script vuelca la base con `mariadb-dump`, copia `storage/app/data` completo
(con subcarpetas) y los adjuntos, y comprueba cada paso: si algo falla, aborta
en vez de anunciar un respaldo vacío.

## Desarrollo local

```bash
composer install
npm install
composer run dev
```

## Pruebas

```bash
php artisan test
```

**185 pruebas** (546 aserciones), 1 omitida.

## Estructura del código

La lógica vive en controladores, no en el archivo de rutas.

| Ruta | Responsabilidad |
| --- | --- |
| `routes/web.php` | Solo declara rutas, middleware y nombres |
| `app/Http/Controllers` | Un controlador por dominio (auth, foro, chat, libreta, admin…) |
| `app/Http/Middleware` | `ResolveActiveUser`, `EnsureRegisteredUser`, `EnsureAdmin`, `EnsureInstalled`, `EnsurePasswordChanged` |
| `app/Support` | Servicios pequeños: contenedores, pases, moderación, presencia, invitado |
| `app/Services` | Persistencia (usuarios, foro, chat, amistades, perfil, notas) |
| `config/virthub.php` | Toda la configuración del proyecto |

### Configuración

El proyecto **nunca** lee `env()` fuera de `config/`. Con
`php artisan config:cache` los valores de `env()` pasan a ser `null` en tiempo
de ejecución, así que toda la configuración propia vive en `config/virthub.php`
y se consume con `config('virthub.*')`.

### Almacén de usuarios

`App\Services\UserStore` es la interfaz que usa la aplicación.
`DatabaseUserStore` es la implementación de producción y `JsonUserStore` queda
como almacén heredado (y para el importador `importLegacyJson`). La suite cubre
ambas.

## Seguridad

- Contraseñas con hash; **cambio obligatorio** de la contraseña temporal, y el
  cambio exige la actual.
- Segundo factor TOTP opcional, con códigos de recuperación en hash.
- Bloqueo temporal tras intentos fallidos, con límite por IP.
- El acceso de invitado está limitado por IP y **solo admite un invitado a la
  vez**.
- El instalador exige `INSTALL_KEY`; la vía sin clave solo se abre con
  `INSTALL_ALLOW_LOCALHOST=true` desde el propio host.
- Nunca se crean administradores con contraseñas por defecto.
- Moderación NSFW y antivirus antes de que el archivo llegue a ser público.
- Eventos de acceso y actividad en un canal de log dedicado (`security`).

## Objetivos

El detalle de los objetivos académicos está en
[OBJETIVOS_PROYECTO.md](OBJETIVOS_PROYECTO.md). La investigación de fondo
(ancho de banda, cliente ligero y qué aporta el proyecto) está en
[docs/investigacion-ancho-de-banda-y-cliente-ligero.md](docs/investigacion-ancho-de-banda-y-cliente-ligero.md).

## Documentación

- [Guía de despliegue](docker/DESPLIEGUE.md)
- [Manual de administrador](docs/Manual-de-Administrador-Virthub-2.0.docx)
- [Manual de usuario](docs/Manual-de-Usuario-Virthub-2.0.docx)
- [Presentación](docs/Presentacion-Virthub-2.0.pptx)

## Autor

Estudiante de Ingeniería en Ciberseguridad. Proyecto de hobby para montar un
homelab con contenedores que mis amigos puedan usar como su propia computadora.

Firma: HankMon03 / FrankMon03
