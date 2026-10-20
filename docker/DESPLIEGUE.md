# Despliegue con Docker

Guía completa del stack: aplicación, base de datos, Caddy, escritorios Webtop y
el túnel de Cloudflare.

---

## 1. Qué se levanta

| Servicio | Imagen | Para qué |
|---|---|---|
| `caddy` | `caddy:2-alpine` | Única puerta de entrada. Publica solo en `127.0.0.1:8080` |
| `app` | construida (`Dockerfile`) | Laravel sobre Apache |
| `mariadb` | `mariadb:11.4` | Base de datos |
| `ct0` | `lscr.io/linuxserver/webtop:ubuntu-mate` | Escritorio del administrador |
| `ct2`–`ct7` | `virthub-desktop:clon` | Escritorios locales (ver aviso) |
| `ct8`–`ct11` | *(otra máquina)* | Escritorios remotos, definidos en el `Caddyfile` |

> **AVISO IMPORTANTE sobre `virthub-desktop:clon`**
>
> Esa imagen **no viene de internet**: se crea a mano con `docker commit` a partir
> de un escritorio ya configurado. En una máquina nueva **no existe** y `ct2`–`ct7`
> fallarán al arrancar.
>
> Dos opciones:
>
> ```bash
> # A) Crearla desde el escritorio que ya tengas montado (con sus apps)
> docker commit virthub-ct0-1 virthub-desktop:clon
>
> # B) No usarla: en docker-compose.yml, cambiar esos seis servicios a
> #    image: lscr.io/linuxserver/webtop:ubuntu-mate
> ```

---

## 2. Requisitos

- Docker con Compose (Docker Desktop en Windows, docker-ce en Linux)
- **RAM**: cada escritorio Webtop pide ~1,5 GB. Con 8 escritorios, 16 GB es lo
  mínimo razonable; si no se usan todos a la vez, Docker solo consume lo que usan
- **Disco**: ~6 GB por imagen de escritorio + lo que ocupe cada `/config`
- Un dominio con capacidad de túneles (aquí se usa Cloudflare Tunnel)

---

## 3. Preparar el entorno

```bash
cp .env.example .env
```

Edita `.env` y cambia **como mínimo**:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://TU-DOMINIO

# Clave de la aplicación (obligatoria)
APP_KEY=

# Instalador: clave privada para crear el primer administrador
INSTALL_KEY=una-clave-larga-y-aleatoria
ADMIN_PASSWORD=algo-que-no-sea-la-del-ejemplo

# Base de datos: cambia las tres contraseñas
DB_PASSWORD=...
DB_ROOT_PASSWORD=...

# Dominio de las cookies. DEBE empezar por punto para que la sesión llegue a
# los subdominios de los escritorios (ct0.tudominio, ct2.tudominio...)
SESSION_DOMAIN=.TU-DOMINIO
FORCE_HTTPS=true
```

Genera la clave de la aplicación. `APP_KEY` es una cadena aleatoria de 32 bytes en
base64; esta forma funciona en cualquier máquina, sin necesidad de tener PHP ni
las dependencias instaladas:

```bash
docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Copia el resultado en `APP_KEY=base64:...`.

Si ya tienes el proyecto con dependencias en el host, también vale
`php artisan key:generate`.

### Rutas de los escritorios

```ini
# Windows
CONTAINER_DATA_DIR=C:\webtop

# Linux
CONTAINER_DATA_DIR=/srv/webtop
```

```ini
# Carpeta del escritorio de invitado. Se monta en la app para poder borrar su
# información personal cuando termina la sesión de cada invitado.
# Windows:
GUEST_DATA_DIR=C:\webtop\ct7
# Linux:
GUEST_DATA_DIR=/srv/webtop/ct7
```

> El valor por defecto de `GUEST_DATA_DIR` es `C:\webtop\ct7`. **En Linux hay que
> cambiarlo**, o el montaje fallará.

### Escritorios remotos (opcional)

Si `ct8`–`ct11` viven en otra máquina, edita `docker/Caddyfile` y pon su IP real:

```caddyfile
import webtop_proxy ct8 192.168.1.100:3000
import webtop_proxy ct9 192.168.1.100:3100
```

Y después, en la base de datos, el host de sondeo de esos escritorios:

```bash
docker compose -p virthub exec -u www-data app php artisan tinker --execute="
App\Models\WebtopContainer::whereIn('name', ['ct8','ct9','ct10','ct11'])
    ->update(['internal_host' => '192.168.1.100']);
"
```

> Fija esa IP por DHCP en el router. Si cambia, hay que actualizarla en **dos**
> sitios: el `Caddyfile` y la base de datos.

---

## 4. Construir y levantar

```bash
docker compose -p virthub --env-file .env up -d --build
```

La primera vez tarda: descarga las imágenes de escritorio (~6 GB cada una).

**Las migraciones se ejecutan solas**: el `CMD` del contenedor hace
`php artisan migrate --force` y `storage:link` antes de arrancar Apache.

Comprueba que todo subió:

```bash
docker compose -p virthub ps
```

---

## 5. Crear el administrador

```
https://TU-DOMINIO/install?key=TU_INSTALL_KEY
```

Sin la clave correcta no se abre nada. `INSTALL_ALLOW_LOCALHOST=true` solo
habilita la vía sin clave desde el propio servidor.

---

## 6. Registrar los escritorios

Cada escritorio debe existir en la base de datos para que el panel lo muestre y
para que el proxy sepa autorizarlo:

```bash
docker compose -p virthub exec -u www-data app php artisan virthub:sync-containers
```

> **Usa siempre `-u www-data`.** Ejecutar artisan como `root` deja archivos de
> caché con ese dueño y después la web falla con `Permission denied`.

---

## 7. Exponerlo a internet (Cloudflare Tunnel)

Caddy escucha solo en loopback (`127.0.0.1:8080`); el túnel corre en el host.

`C:\Users\<usuario>\.cloudflared\config.yml` (o `/etc/cloudflared/config.yml`):

```yaml
tunnel: TU-TUNNEL-ID
credentials-file: C:\Users\<usuario>\.cloudflared\TU-TUNNEL-ID.json

ingress:
  - hostname: TU-DOMINIO
    service: http://127.0.0.1:8080
  - hostname: ct0.TU-DOMINIO
    service: http://127.0.0.1:8080
  - hostname: ct2.TU-DOMINIO
    service: http://127.0.0.1:8080
  # ... un bloque por cada escritorio hasta ct11, mas el comodin si se quiere
  - service: http_status:404
```

Cada hostname necesita además su **registro DNS** (tipo `Túnel`, apuntando al
túnel). Sin el registro, el subdominio no resuelve.

```bash
cloudflared tunnel ingress validate
# reiniciar el servicio para que lea los cambios: config.yml solo se lee al arrancar
```

> **Alternativa:** en vez de un túnel, publica Caddy directamente
> (`ports: - "80:80"` y `- "443:443"`) y deja que emita los certificados. En ese
> caso quita el `:8080` de los sitios del `Caddyfile`.

---

## 8. Comprobar que funciona

```bash
# La aplicación responde
curl -I https://TU-DOMINIO/
# -> HTTP 200

# Un escritorio responde y está protegido
curl -I https://ct0.TU-DOMINIO/
# -> HTTP 403  (403 = protegido y en pie; es lo correcto)

# Estado interno de los escritorios
docker compose -p virthub exec -u www-data app php artisan tinker --execute="
\$s = app(App\Support\SystemStatusMonitor::class)->snapshot();
foreach (\$s['container_status'] as \$n => \$v) {
    echo str_pad(\$n, 6) . (\$v['online'] ? 'en linea' : 'caido') . PHP_EOL;
}
"
```

---

## 9. Problemas típicos

| Síntoma | Causa | Solución |
|---|---|---|
| **502** en un escritorio | Su contenedor está apagado o la IP cambió | `docker compose up -d ct5` / revisar `Caddyfile` |
| **403** al abrir el escritorio | El pase no vale o el usuario no es dueño | Recargar desde la página de contenedor |
| **404** en `/api/websockets` | Selkies no arrancó | Ver abajo, permisos de `/config` |
| **500** `Permission denied` en `storage` | Se ejecutó artisan como root | `docker compose restart app` |
| Escritorio sin cargar, «WebSocket disconnected» | Dueño incorrecto en `/config` | Ver `docker/CLONAR-ESCRITORIOS.md` |
| El panel marca caídos escritorios activos | IP del equipo remoto cambiada | Actualizar `Caddyfile` + `internal_host` |

### Ver los registros

```bash
docker compose -p virthub logs -f app
docker compose -p virthub logs -f caddy
```

### Detener sin perder datos

```bash
docker compose -p virthub down          # conserva volúmenes y escritorios
docker compose -p virthub down -v       # BORRA la base de datos y storage
```

Los escritorios **no** están en volúmenes Docker: sus datos viven en
`CONTAINER_DATA_DIR` (`C:\webtop` o `/srv/webtop`) y sobreviven a `down -v`.

---

## 10. Actualizar una instancia ya desplegada

```bash
git pull
docker compose -p virthub --env-file .env up -d --build app caddy
docker compose -p virthub exec -u www-data app php artisan virthub:sync-containers
```

Las migraciones nuevas se aplican solas al recrear `app`.

---

## 11. Resumen de comandos

```bash
# Preparar
cp .env.example .env          # y editar claves, dominio y rutas

# Levantar todo
docker compose -p virthub --env-file .env up -d --build

# Crear el administrador
#   https://TU-DOMINIO/install?key=TU_INSTALL_KEY

# Registrar los escritorios
docker compose -p virthub exec -u www-data app php artisan virthub:sync-containers

# Ver estado
docker compose -p virthub ps
docker compose -p virthub logs -f app
```
