# Clonar un escritorio Webtop

Pasos para copiar un escritorio ya configurado (con sus aplicaciones y ajustes) a
otros contenedores.

## Como funciona el estado de un escritorio

Vive en **dos sitios**, y hay que clonar los dos:

| Pieza | Donde | Como se clona |
|---|---|---|
| Aplicaciones del sistema (VS Code, Packet Tracer…) | `/usr`, `/usr/local/bin` | `docker commit` |
| Home y ajustes (MATE, accesos, AppImages) | `/config` (bind mount) | Copia de la carpeta |

Solo copiar `/config` **no basta**: VS Code esta en `/usr/bin/code`, fuera del
home. Solo hacer `docker commit` tampoco: no incluye los bind mounts.

```powershell
# 1. Congelar el sistema del escritorio original
docker commit virthub-ct0-1 virthub-desktop:clon

# 2. Copiar el home a cada clon
robocopy C:\webtop\ct0 C:\webtop\ct2 /E /MT:16

# 3. Crear los contenedores con la imagen congelada
docker compose -p virthub --env-file .env up -d ct2
```

## EL PROBLEMA: la propiedad de los archivos

**Al copiar con `robocopy` (o cualquier copia desde Windows), los archivos quedan
como `root:root` dentro del contenedor. En el original son de `abc`.**

Eso rompe el arranque de forma silenciosa:

```
PulseAudio no arranca            (su carpeta debe ser del usuario)
   -> no crea /defaults/pid
      -> selkies espera en:  until [ -f /defaults/pid ]; do sleep .5; done
         -> sin Selkies no hay WebSocket
            -> el navegador muestra "WebSocket disconnected. Attempting to reconnect..."
```

El escritorio se ve "arrancado" (responde 200, mate-session corre), pero **no se
puede entrar**: el 404 esta en `/api/websockets`, no en la pagina.

### El arreglo

Basta con devolver la propiedad a las carpetas que lo necesitan. **Tarda menos de
un segundo:**

```powershell
docker exec virthub-ct2-1 sh -c "chown -R abc:abc /config/.config/pulse /config/.config/dconf /config/.dbus; chmod 700 /config/.config/pulse"
docker restart virthub-ct2-1
```

No hace falta `chown -R /config` completo: son 158.000 archivos y tarda unos
**12 minutos por contenedor** en un bind mount de Windows.

### Comprobar si un escritorio esta bien

```powershell
# 101 = funciona. 404 = Selkies no arranco (propiedad mal).
curl -s -o /dev/null -w '%{http_code}' --max-time 10 `
  -H "Connection: Upgrade" -H "Upgrade: websocket" `
  -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" `
  http://localhost:8000/...   # o desde dentro de la red de Docker
```

Y para ver el motivo:

```powershell
docker exec virthub-ct2-1 sh -c "s6-svstat /run/service/svc-pulseaudio"   # up / down
docker exec virthub-ct2-1 ls /defaults/pid                                # debe existir
docker exec virthub-ct2-1 ss -tln | grep 8082                             # Selkies
```

## La forma correcta de copiar (sin el problema)

Copiar **dentro** de un contenedor conserva la propiedad de Unix:

```powershell
# Origen en solo lectura, destino escribible, y cp -a (preserva dueno y permisos)
docker run --rm -v C:\webtop\ct0:/origen:ro -v C:\webtop\ct2:/destino alpine `
  sh -c "cp -a /origen/. /destino/"
```

Sigue siendo una copia sobre un bind mount de Windows, asi que puede arrastrar el
mismo problema; si ocurre, aplicar el `chown` de arriba.

## Nota sobre la ruta del WebSocket

Depende de la version de Selkies de cada imagen:

| Imagen | Ruta |
|---|---|
| `ubuntu-xfce` (Selkies antiguo) | `/websockets` |
| `ubuntu-mate` y equipo 2 (Selkies 2.0) | `/api/websockets` |

Caddy reenvia la ruta tal cual, asi que funciona sin configuracion; solo hay que
saber cual es al diagnosticar. **No confundir con `/api/ws`**, que es la
senalizacion de WebRTC y responde 409 ("WebRTC mode is inactive").
