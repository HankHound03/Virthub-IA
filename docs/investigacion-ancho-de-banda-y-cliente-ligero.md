# Ancho de banda, cliente ligero y lo que realmente aporta Virthub

Notas de investigación para la defensa del proyecto.
Fecha: noviembre de 2026.

---

## 1. Cómo limitar el ancho de banda en Docker

### Lo primero: no existe una opción nativa

Docker **no** tiene un parámetro de "ancho de banda por contenedor". Los límites
de `docker run` y de Compose son de CPU, memoria, procesos y **disco**
(`--device-read-bps` es disco, no red):

```yaml
services:
  ct0:
    cpus: 1.5
    mem_limit: 4g
    pids_limit: 512
```

Para red hay que bajar un nivel: **Linux `tc` (traffic control)**, que actúa sobre
la interfaz virtual (`veth`) del contenedor, o sobre el puente de la red.

### Las tres formas prácticas

**A. `tbf` sobre el veth del contenedor (lo más directo)**

```bash
# interfaz veth del contenedor, en el host
PID=$(docker inspect -f '{{.State.Pid}}' virthub-ct0-1)
IFLINK=$(nsenter -t $PID -n cat /sys/class/net/eth0/iflink)
VETH=$(ip -o link show | awk -v i="$IFLINK" -F': ' '$1 == i {print $2}' | cut -d@ -f1)

# 8 Mbps de bajada hacia el contenedor
tc qdisc add dev $VETH root tbf rate 8mbit burst 32kb latency 400ms

# y para la salida (el video que ve el alumno), se aplica dentro del contenedor
docker exec --privileged virthub-ct0-1 tc qdisc add dev eth0 root tbf rate 8mbit burst 32kb latency 400ms
```

**B. `netem`, para simular una conexión mala (útil para probar)**

```bash
# 150 ms de latencia, 30 ms de variación, 2 % de pérdida y 3 Mbps
tc qdisc add dev $VETH root handle 1: netem delay 150ms 30ms loss 2%
tc qdisc add dev $VETH parent 1:1 handle 2: tbf rate 3mbit burst 32kb latency 400ms
```

**C. Un contenedor aparte que aplique la regla dentro del namespace**
La más portable, y **la única cómoda si Docker corre sobre Windows/macOS** (ahí el
kernel real está en una máquina virtual y no hay `veth` visibles desde Windows):

```yaml
services:
  ct0:
    image: virthub-desktop:clon
    cap_add: [NET_ADMIN]

  ct0-limite:
    image: alpine:latest
    network_mode: "service:ct0"      # comparte la red de ct0
    cap_add: [NET_ADMIN]
    entrypoint: >
      sh -c "apk add --no-cache iproute2 &&
             tc qdisc add dev eth0 root tbf rate 8mbit burst 32kb latency 400ms &&
             sleep infinity"
```

Fuentes: guía práctica de `tc` con contenedores
([OneUptime](https://github.com/OneUptime/blog/commit/cb13ccfba23d396853ab8c36970c09acefe720ee))
y una herramienta que empaqueta justo esto
([brakebear](https://github.com/ethpandaops/brakebear)).

### D. La forma que de verdad te sirve: limitar el **video**, no la red

El 90 % del tráfico de un escritorio es el video de Selkies. Se puede limitar sin
tocar `tc`, solo con variables del contenedor
([documentación de Selkies](https://github.com/linuxserver/docker-documentation/blob/master/docs/selkies/user-guide/webrtc.md)):

| Variable | Efecto |
|---|---|
| `SELKIES_VIDEO_BITRATE` | Techo de bits por segundo del codificador |
| `SELKIES_RATE_CONTROL_MODE=cbr,crf` | **CBR** respeta ese techo; CRF lo ignora (calidad constante) |
| `SELKIES_FRAMERATE` | Menos cuadros, menos bits |
| `SELKIES_ENCODER` | Orden de códecs a probar |

Hoy en Virthub los escritorios arrancan con:

```
SELKIES_ENCODER=h264enc,h265enc,vp8enc,vp9enc,av1enc,jpeg
SELKIES_RATE_CONTROL_MODE=crf,cbr      ← CRF primero: el bitrate es variable
```

Es decir: **ahora mismo no hay techo**. Si un alumno abre un video a pantalla
completa, el escritorio puede pedir 10–20 Mbps. Pasar a CBR con un techo (por
ejemplo 4 Mbps) es la medida más efectiva y no requiere privilegios.

### Comparación

| Método | Aplica a | ¿Necesita root? | ¿Funciona en Docker Desktop? |
|---|---|---|---|
| `tbf`/`netem` en el veth | Todo el tráfico del contenedor | Sí (host Linux) | Solo desde la VM |
| Sidecar con `NET_ADMIN` | Todo el tráfico | No en el host | **Sí** |
| `SELKIES_VIDEO_BITRATE` + CBR | El video (lo que importa) | No | **Sí** |
| Proxy delante (nginx) | HTTP/WebSocket, por URL | No | Sí, pero no limita el WS de video |

---

## 2. «Remoto» y «local»: dónde se considera cada uno

### La definición corta

- **Local**: el programa se ejecuta en el equipo que tienes delante. Sus datos y
  su CPU están ahí.
- **Remoto**: el programa se ejecuta en otra máquina; lo que llega al equipo local
  es **la imagen ya dibujada** y las pulsaciones de teclado.

En Virthub es **remoto puro**: la aplicación (VS Code, Packet Tracer, el
navegador del escritorio) corre en el contenedor; el equipo del alumno solo
dibuja el video.

### Cuándo se considera cada uno

| Criterio | Tira hacia local | Tira hacia remoto |
|---|---|---|
| **Latencia** | < 20 ms (juegos, escribir) | > 50 ms tolerable |
| **Ancho de banda** | Poco: solo datos | Mucho: hay que mandar video |
| **Cómputo necesario** | El cliente puede | El cliente no puede |
| **Datos** | Son del cliente y grandes | Ya viven en el servidor |
| **Privacidad** | No deben salir del equipo | Debe centralizarse |
| **Coste** | Un equipo bueno por persona | Un servidor para muchos |

### La zona gris: híbrido

Existen modelos intermedios que conviene saber nombrar, porque demuestran que la
decisión no es binaria:

- **Descarga y ejecuta**: la app se descarga una vez y corre local (lo normal).
- **Local con datos remotos**: Google Drive, Nextcloud; calcula el cliente.
- **Remoto con caché local**: VDI con *pixel caching*; el servidor calcula.
- **Reparto**: el cliente decodifica el video (con GPU) y el servidor codifica.
  **Esto es exactamente Virthub**: el trabajo pesado es remoto, pero la
  decodificación H.264 corre en el equipo del alumno.

> **Frase para la defensa:** «Virthub no es cliente ligero porque el cliente no
> sepa hacer nada: es cliente ligero porque el cliente *no necesita* hacer nada
> más que decodificar».

---

## 3. La terminal tonta

### Qué es

Un **terminal tonto** (o *cliente ligero*, *thin client*) es un equipo que:

1. **No ejecuta** las aplicaciones: solo las muestra.
2. **No guarda** datos propios: todo está en el servidor.
3. **Depende** por completo de la red para funcionar.

«Tonta» no es un insulto: significa que **no tiene lógica de negocio propia**. La
inteligencia está en el servidor; el terminal solo pinta y captura.

### La historia, que es lo que pregunta tu punto 6

| Época | Qué era | Lo que aportó |
|---|---|---|
| **1971** | IBM 3270 | Terminal de texto contra un mainframe. Cero cómputo local |
| **1980s–90s** | Terminales X11 | Ya había gráficos, pero el servidor X dibujaba |
| **1995** | Citrix WinFrame | Windows multiusuario sobre un servidor |
| **1999** | LTSP (Linux Terminal Server Project) | **El más cercano a Virthub**: un servidor reparte Linux a muchos clientes ligeros |
| **1999** | Sun Ray | Hardware dedicado a cliente ligero |
| **2006+** | VDI (VMware Horizon, etc.) | Un escritorio virtual por usuario |
| **2010+** | Apache Guacamole | Escritorio remoto **por el navegador**, sin instalar nada |
| **2024** | Selkies | Lo que usa Virthub: escritorio por WebSocket/WebRTC |

**Conclusión honesta:** la idea tiene más de 50 años. Lo que cambia es **el
cliente**: de un aparato de 1 000 dólares a una pestaña del navegador.

### Por qué el navegador lo cambia todo

| Antes (LTSP, Sun Ray) | Ahora (Virthub) |
|---|---|
| Cliente ligero dedicado | Un navegador cualquiera |
| Instalación en el equipo | Cero instalación |
| Red local obligatoria | Funciona por internet |
| Un servidor por aula | Contenedor por alumno |
| Redibujar la pantalla completa | H.264, solo lo que cambia |

---

## 4. Consumo de recursos: local y remoto

### Medido en este equipo (Docker, ahora mismo)

| Contenedor | CPU en reposo | RAM |
|---|---|---|
| `ct0` (escritorio con apps) | 0.76 % | 599 MiB |
| `ct2` | 0.73 % | 335 MiB |
| `ct3` | 0.70 % | 511 MiB |
| `ct4` | 0.64 % | 301 MiB |
| `ct5` | 0.63 % | 376 MiB |
| `ct6` | 0.67 % | 307 MiB |
| `ct7` | 0.61 % | 333 MiB |
| `app` (Laravel) | 0.00 % | 86 MiB |
| `caddy` | 0.00 % | 13 MiB |
| `mariadb` | 0.01 % | 122 MiB |
| `clamav` | 0.01 % | 1 012 MiB |
| `nsfw-sherlock` | 0.01 % | 1 821 MiB |
| **Máquina** | — | **15.29 GiB** |

**Lecturas que salen de aquí:**

1. Un escritorio cuesta **300–600 MiB de RAM en reposo y menos del 1 % de CPU**.
   Siete escritorios ≈ 2.6 GiB.
2. **La moderación es lo más caro del sistema**: `nsfw-sherlock` (1.8 GiB) más
   `clamav` (1 GiB) son **2.8 GiB**, más que cuatro escritorios. Es un dato que
   conviene tener preparado porque casi nadie lo espera.
3. En reposo **no hay tráfico**: los contenedores sin sesión abierta mueven
   kilobytes.

### Con una sesión abierta (estimación a partir del consumo típico)

| Consumo | Cliente (equipo del alumno) | Servidor |
|---|---|---|
| **CPU en reposo** | 1–3 % (decodificar video) | ~1–3 % por sesión activa |
| **CPU con video a pantalla completa** | 10–25 % (o 0 % con GPU) | 15–40 % por sesión |
| **RAM** | 150–400 MB (pestaña) | 300–600 MB (medido) |
| **Red** | **2–8 Mbps** con H.264 a 1080p | lo mismo, en subida |

> Regla práctica: **un núcleo y 1 GB por escritorio** si se quiere margen, y
> **2–4 Mbps por sesión** para que se vea fluido sin CBR.

### El truco que hace que esto funcione en equipos viejos

El video va **codificado**, no como imagen completa. Selkies con H.264 manda solo
los cuadros que cambian; por eso un Core 2 Duo puede *decodificar* algo que nunca
podría *ejecutar*.

Y cuando el equipo es malo de verdad, el modo ligero de Virthub apaga desenfoques
y acorta animaciones: no cambia el consumo remoto, pero **baja mucho el del
navegador**, que es donde de verdad se nota.

---

## 5. Limitaciones del ancho de banda del proveedor

### Los cuatro límites que se suman

| Límite | Dónde | Valores típicos |
|---|---|---|
| **Subida del cliente** | ADSL/4G del alumno | 1–10 Mbps de subida; el video va **de bajada**, pero el teclado y los archivos van de subida |
| **Puerto del servidor** | VPS | 100 Mbps–1 Gbps **compartido**, rara vez garantizado |
| **Transferencia mensual** | VPS | 1–20 TB/mes; excedente se cobra o se atenúa |
| **Túnel/proxy** | Cloudflare | Sin techo duro en el plan gratuito, pero con política de uso y límite de subida (100 MB por petición) |

### Las cuentas que hay que poder hacer en la defensa

Un escritorio a **3 Mbps** durante **4 horas** al día:

```
3 Mbps × 4 h = 3 × 0.45 GB/h × 4 ≈ 5.4 GB por sesión
20 alumnos × 5.4 GB × 20 días = 2 160 GB ≈ 2.1 TB al mes
```

Y todo eso en **subida del servidor**, que es el sentido que casi siempre se
limita primero.

### Qué hacer cuando el proveedor aprieta

| Medida | Efecto |
|---|---|
| Bajar `SELKIES_VIDEO_BITRATE` a 2–3 Mbps | Reduce el consumo a la mitad, se nota poco en ofimática |
| CBR en vez de CRF | Techo predecible: se puede presupuestar |
| Limitar `SELKIES_FRAMERATE` a 20–30 | Menos bits sin perder usabilidad |
| `tc` por contenedor | Reserva justa por alumno, evita que uno consuma por diez |
| Cerrar sesiones inactivas | Un escritorio sin sesión no gasta red |
| Colocar el servidor cerca de los alumnos | Menos latencia y menos retransmisiones |

### Un detalle que casi nadie considera

Con **WebSocket** (lo que usa Virthub), el video viaja por TCP: una pérdida de
paquete **frena todo** hasta reenviarlo. Con **WebRTC** (UDP) una pérdida solo
estropea un cuadro. Por eso Selkies documenta que en redes móviles o congestionadas
conviene WebRTC — a cambio de abrir un puerto UDP y, a veces, un servidor TURN.

**Para la defensa:** WebSocket es más simple y pasa por cualquier proxy;
WebRTC es mejor en redes malas. La decisión es de red, no de gusto.

---

## 6. Qué aporta Virthub frente a la terminal tonta de hace 20 años

### Lo que **no** es nuevo (y conviene admitirlo)

- La idea de ejecutar en el servidor y dibujar en el cliente. **1960s–70s.**
- El escritorio Linux remoto por red. **LTSP, 1999.**
- El escritorio remoto por navegador. **Guacamole, 2010.**
- El cliente ligero como forma de ahorrar hardware. **Sun Ray, 1999.**

Si alguien pregunta «¿esto no es un cliente ligero de siempre?», la respuesta
correcta es **«sí, y eso es una virtud: el concepto está probado»**. La pregunta
interesante es qué cambió para que hoy sea práctico.

### Lo que **sí** es nuevo o distinto

| Aporte | Por qué importa |
|---|---|
| **El cliente es un navegador** | Cero instalación, funciona en Windows, Linux, Android o una tablet prestada. LTSP necesitaba hardware y red local |
| **Un contenedor por alumno** | Aislamiento real y reproducible con `docker compose`; LTSP compartía un único sistema entre todos |
| **Sin VPN ni cliente especial** | Guacamole o VNC pedían instalar algo o abrir puertos; aquí es una URL con pase firmado |
| **Adaptación al equipo modesto** | Detección de núcleos/memoria y modo ligero. Ningún cliente ligero clásico se adaptaba: o valía o no valía |
| **Moderación en el servidor** | Las fotos no salen a servicios externos: NSFW y antivirus corren en el mismo sitio |
| **Todo en un dominio** | El escritorio, el chat, el foro y la libreta comparten identidad y sesión |
| **Coste** | Un servidor modesto da servicio a un aula; el alumno no compra nada |
| **Se despliega con un comando** | `docker compose up -d`. Un laboratorio de 1999 era un proyecto de semanas |

### La mejora concreta frente al escritorio remoto de hace 20 años

| Hace 20 años | Virthub |
|---|---|
| X11 redibuja **la pantalla completa** | **H.264**: solo los cuadros que cambian |
| Ancho de banda: 10–100 Mbps por cliente | **2–8 Mbps** |
| Cliente dedicado de 500–1 000 USD | Cualquier navegador |
| Un servidor por aula, sin aislamiento | **Un contenedor por alumno** |
| Se rompe si falta la red | También, pero reconecta solo y conserva la sesión |
| Sin control de contenido | Moderación de imágenes y antivirus integrados |

### La frase que resume el proyecto

> «La terminal tonta vuelve, pero sin terminal: lo único que el alumno necesita
> tener es un navegador. Lo demás —la CPU, la memoria, las aplicaciones caras—
> se queda en el servidor, repartido en contenedores y protegido por la misma
> sesión que el resto del sitio.»

### Y la limitación honesta

Virthub **no elimina** la dependencia del hardware: la **mueve**. El alumno ya no
necesita una computadora potente, pero sí una conexión estable de 3–5 Mbps. Esa
es la contrapartida y hay que decirla antes de que la pregunten.

---

## Anexo: lo que se podría implementar mañana

| Mejora | Coste | Beneficio |
|---|---|---|
| `SELKIES_VIDEO_BITRATE` + CBR por escritorio | 1 variable | Consumo predecible, presupuesto de red |
| `tc` por contenedor con un sidecar | ~20 líneas de compose | Ningún alumno acapara el ancho de banda |
| Cerrar sesiones inactivas | Ya hay base (presencia) | Menos red y menos CPU |
| WebRTC para redes móviles | Puerto UDP + TURN | Mejor experiencia en 4G |
| Métricas de consumo por sesión | Selkies ya exporta estadísticas | Datos para la defensa y para dimensionar |

---

## Conclusión

La investigación deja tres conclusiones que se sostienen una en otra.

**La primera es que la idea no es nueva, y no necesita serlo.** Virthub pertenece
a una familia que empieza en el IBM 3270 de 1971, pasa por LTSP en 1999 y llega a
los escritorios por navegador de la década pasada. Reconocerlo no debilita el
proyecto: lo sitúa. Un concepto que lleva más de cincuenta años resolviendo el
mismo problema no está de moda, está probado; lo que cambia, y es lo que esta
versión aporta, es **quién hace de cliente**. Donde antes había un aparato
dedicado y una red de aula, ahora hay una pestaña del navegador y cualquier
conexión.

**La segunda es que el recurso escaso no es el procesador, es el ancho de banda.**
Las mediciones de este equipo lo muestran con claridad: un escritorio en reposo
cuesta entre 300 y 600 MiB de memoria y menos del 1 % de CPU, de modo que siete
escritorios caben en 2.6 GiB, mientras que el equipo que los atiende dispone de
15.29 GiB. El cómputo, por tanto, es un problema resuelto a esta escala. Lo que
limita de verdad es el video: entre 2 y 8 Mbps por sesión activa, que se
convierten en más de dos terabytes mensuales para un aula de veinte alumnos, y
que salen todos por la **subida** del servidor. Cualquier decisión de diseño que
se tome de aquí en adelante —techo de bits, control de tasa constante, límites por
contenedor, sesiones inactivas— debería ordenarse alrededor de ese dato.

**La tercera es que Virthub no elimina la dependencia del hardware: la mueve.** El
alumno deja de necesitar una computadora capaz de ejecutar VS Code o Packet
Tracer, pero pasa a necesitar una conexión estable de 3 a 5 Mbps y un navegador
que decodifique H.264. Es una contrapartida real, no un detalle, y conviene
enunciarla antes de que la pregunten: el proyecto no hace desaparecer la brecha
tecnológica, la sustituye por otra más barata y más fácil de cerrar desde una
institución.

De esas tres conclusiones se desprende una cuarta, que es la que interesa al
proyecto: **la contribución de Virthub no está en haber inventado el cliente
ligero, sino en haberlo hecho institucionalmente viable**. Un contenedor por
alumno en lugar de un sistema compartido, una URL con pase firmado en lugar de
un cliente instalado, moderación y antivirus en el mismo servidor en lugar de
servicios de terceros, y un despliegue que cabe en un comando: el conjunto es lo
nuevo, no las piezas. La terminal tonta vuelve, pero sin terminal.

---

## Fuentes

- Guía de `tc` con contenedores Docker:
  [OneUptime](https://github.com/OneUptime/blog/commit/cb13ccfba23d396853ab8c36970c09acefe720ee)
- Herramienta de límites de red para contenedores:
  [ethpandaops/brakebear](https://github.com/ethpandaops/brakebear)
- Transporte WebRTC, bitrate y TURN en Selkies:
  [documentación de LinuxServer](https://github.com/linuxserver/docker-documentation/blob/master/docs/selkies/user-guide/webrtc.md)
- Cliente ligero, historia y concepto:
  [Wikipedia: thin client](https://en.wikipedia.org/wiki/Thin_client)
- Terminal IBM 3270: [Wikipedia](https://en.wikipedia.org/wiki/IBM_3270)
- LTSP: [Wikipedia](https://en.wikipedia.org/wiki/Linux_Terminal_Server_Project)
- Medidas propias: `docker stats` en el equipo de desarrollo, 23/11/2026
