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

Devolver **todo** `/config` al usuario. Se hace en segundo plano y en paralelo en
todos los contenedores a la vez:

```powershell
foreach ($c in @('ct2','ct5','ct6','ct7')) {
    # -d: se ejecuta dentro del contenedor sin bloquear esta terminal
    docker exec -d "virthub-$c-1" sh -c "chown -R abc:abc /config; touch /tmp/chown-hecho"
}

# Comprobar cuando termina
docker exec virthub-ct2-1 test -f /tmp/chown-hecho && echo listo

# Reiniciar para que los servicios arranquen con la propiedad correcta
docker restart virthub-ct2-1 virthub-ct5-1 virthub-ct6-1 virthub-ct7-1
```

Tarda unos **9 minutos** para cuatro contenedores en paralelo (158.000 archivos
por contenedor sobre un bind mount de Windows). Uno solo tarda unos 12 minutos.

**No basta con arreglar solo `/config/.config/pulse`.** Eso hace arrancar a
Selkies y el WebSocket responde 101, pero luego las aplicaciones fallan una a una
con errores del tipo:

```
The path for the directory containing caja settings need read and write
permissions: /config/.config/caja
```

Caja (el gestor de archivos de MATE) es la primera que se queja, pero cualquier
aplicacion que escriba en su carpeta de `/config/.config` dara un error parecido.

### Comprobar si un escritorio esta bien

```powershell
# 1. WebSocket: 101 = funciona. 404 = Selkies no arranco.
# 2. Propiedad: no debe quedar nada de root.
docker exec virthub-ct2-1 sh -c "find /config -maxdepth 2 -user root | wc -l"   # debe dar 0
```

Y para ver el motivo de un fallo:

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
