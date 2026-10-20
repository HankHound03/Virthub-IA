# -*- coding: utf-8 -*-
"""
Genera la guia de despliegue en Word a partir del contenido documentado.

Se construye con estilos reales (titulos, tablas, bloques de codigo) para que el
documento se pueda editar en Word sin pelear con el formato.
"""

from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

SALIDA = "Virthub-Despliegue-Docker.docx"

AZUL = RGBColor(0x1F, 0x3B, 0x63)
GRIS = RGBColor(0x55, 0x5F, 0x6B)
ROJO = RGBColor(0xA6, 0x1B, 0x1B)
VERDE = RGBColor(0x1B, 0x6B, 0x3A)


# ---------------------------------------------------------------------------
# Utilidades de formato
# ---------------------------------------------------------------------------

def sombrear(elemento, color):
    """Pinta el fondo de una celda o de un parrafo."""
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), color)
    elemento.append(shd)


def borde_parrafo(parrafo, color="D0D7DE"):
    pPr = parrafo._p.get_or_add_pPr()
    bordes = OxmlElement("w:pBdr")
    for lado in ("top", "left", "bottom", "right"):
        b = OxmlElement(f"w:{lado}")
        b.set(qn("w:val"), "single")
        b.set(qn("w:sz"), "4")
        b.set(qn("w:space"), "4")
        b.set(qn("w:color"), color)
        bordes.append(b)
    pPr.append(bordes)


def codigo(doc, texto):
    """Bloque de codigo: monoespaciado, fondo claro y borde."""
    p = doc.add_paragraph()
    pf = p.paragraph_format
    pf.left_indent = Cm(0.3)
    pf.space_before = Pt(4)
    pf.space_after = Pt(8)
    pf.line_spacing = 1.0
    pf.keep_together = True

    sombrear(p._p.get_or_add_pPr(), "F4F6F8")
    borde_parrafo(p)

    for i, linea in enumerate(texto.split("\n")):
        if i:
            p.add_run().add_break()
        run = p.add_run(linea if linea else " ")
        run.font.name = "Consolas"
        run.font.size = Pt(8.5)
        run.font.color.rgb = RGBColor(0x1B, 0x1F, 0x23)
    return p


def aviso(doc, titulo, texto, color_fondo="FFF4E5", color_titulo=ROJO):
    """Recuadro de aviso."""
    p = doc.add_paragraph()
    pf = p.paragraph_format
    pf.left_indent = Cm(0.2)
    pf.space_before = Pt(6)
    pf.space_after = Pt(10)

    sombrear(p._p.get_or_add_pPr(), color_fondo)
    borde_parrafo(p, "E8C99B")

    r = p.add_run(titulo + "  ")
    r.bold = True
    r.font.size = Pt(9.5)
    r.font.color.rgb = color_titulo

    r2 = p.add_run(texto)
    r2.font.size = Pt(9.5)
    return p


def vineta(doc, texto, nivel=0):
    p = doc.add_paragraph(style="List Bullet" if nivel == 0 else "List Bullet 2")
    p.paragraph_format.space_after = Pt(2)
    partes = texto.split("**")
    for i, parte in enumerate(partes):
        run = p.add_run(parte)
        run.bold = i % 2 == 1
        run.font.size = Pt(10)
    return p


def parrafo(doc, texto, cursiva=False, color=None, tam=10):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(6)
    partes = texto.split("**")
    for i, parte in enumerate(partes):
        run = p.add_run(parte)
        run.bold = i % 2 == 1
        run.italic = cursiva
        run.font.size = Pt(tam)
        if color:
            run.font.color.rgb = color
    return p


def tabla(doc, cabeceras, filas, anchos=None):
    t = doc.add_table(rows=1, cols=len(cabeceras))
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.CENTER

    encabezado = t.rows[0].cells
    for i, texto in enumerate(cabeceras):
        encabezado[i].text = ""
        p = encabezado[i].paragraphs[0]
        p.paragraph_format.space_after = Pt(2)
        r = p.add_run(texto)
        r.bold = True
        r.font.size = Pt(9)
        r.font.color.rgb = AZUL
        sombrear(encabezado[i]._tc.get_or_add_tcPr(), "E4ECF7")

    for fila in filas:
        celdas = t.add_row().cells
        for i, valor in enumerate(fila):
            celdas[i].text = ""
            p = celdas[i].paragraphs[0]
            p.paragraph_format.space_after = Pt(2)
            partes = str(valor).split("**")
            for j, parte in enumerate(partes):
                r = p.add_run(parte)
                r.bold = j % 2 == 1
                r.font.size = Pt(9)

    if anchos:
        for fila in t.rows:
            for i, ancho in enumerate(anchos):
                fila.cells[i].width = Cm(ancho)

    doc.add_paragraph().paragraph_format.space_after = Pt(2)
    return t


def titulo(doc, texto, nivel=1):
    h = doc.add_heading(texto, level=nivel)
    for run in h.runs:
        run.font.color.rgb = AZUL
        run.font.name = "Calibri"
    h.paragraph_format.space_before = Pt(14 if nivel == 1 else 10)
    h.paragraph_format.space_after = Pt(6)
    return h


# ---------------------------------------------------------------------------
# Documento
# ---------------------------------------------------------------------------

doc = Document()

# Margenes y estilo base
for seccion in doc.sections:
    seccion.top_margin = Cm(2.2)
    seccion.bottom_margin = Cm(2.2)
    seccion.left_margin = Cm(2.4)
    seccion.right_margin = Cm(2.4)

normal = doc.styles["Normal"]
normal.font.name = "Calibri"
normal.font.size = Pt(10)
normal.paragraph_format.space_after = Pt(6)

# Pie de pagina
pie = doc.sections[0].footer.paragraphs[0]
pie.text = "Virthub — Guía de despliegue con Docker"
pie.alignment = WD_ALIGN_PARAGRAPH.CENTER
for run in pie.runs:
    run.font.size = Pt(8)
    run.font.color.rgb = GRIS

# --- Portada ---------------------------------------------------------------
portada = doc.add_paragraph()
portada.alignment = WD_ALIGN_PARAGRAPH.CENTER
portada.paragraph_format.space_before = Pt(60)
r = portada.add_run("Virthub")
r.font.size = Pt(34)
r.bold = True
r.font.color.rgb = AZUL

sub = doc.add_paragraph()
sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = sub.add_run("Guía de despliegue con Docker")
r.font.size = Pt(17)
r.font.color.rgb = GRIS

linea = doc.add_paragraph()
linea.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = linea.add_run("Plataforma de escritorios virtualizados")
r.font.size = Pt(11)
r.italic = True
r.font.color.rgb = GRIS

doc.add_paragraph().paragraph_format.space_after = Pt(30)

parrafo(
    doc,
    "Este documento describe cómo desplegar el stack completo: la aplicación, la "
    "base de datos, el proxy Caddy, los escritorios Webtop y la publicación por el "
    "túnel de Cloudflare. Incluye los valores que hay que cambiar antes de arrancar, "
    "las comprobaciones y los problemas más frecuentes.",
)

doc.add_page_break()

# --- 1 ---------------------------------------------------------------------
titulo(doc, "1. Qué se levanta")

tabla(
    doc,
    ["Servicio", "Imagen", "Para qué"],
    [
        ["caddy", "caddy:2-alpine", "Única puerta de entrada. Publica solo en 127.0.0.1:8080"],
        ["app", "construida (Dockerfile)", "Laravel sobre Apache"],
        ["mariadb", "mariadb:11.4", "Base de datos"],
        ["ct0", "lscr.io/linuxserver/webtop:ubuntu-mate", "Escritorio del administrador"],
        ["ct2–ct7", "virthub-desktop:clon", "Escritorios locales (ver aviso)"],
        ["ct8–ct11", "(otra máquina)", "Escritorios remotos, definidos en el Caddyfile"],
    ],
    anchos=[2.6, 6.2, 7.2],
)

aviso(
    doc,
    "AVISO: virthub-desktop:clon no viene de internet.",
    "Esa imagen se crea a mano con «docker commit» a partir de un escritorio ya "
    "configurado. En una máquina nueva no existe y ct2–ct7 fallarán al arrancar.",
)

parrafo(doc, "Dos opciones antes de levantar:", tam=10)

codigo(
    doc,
    "# A) Crearla desde un escritorio ya montado (con sus aplicaciones)\n"
    "docker commit virthub-ct0-1 virthub-desktop:clon\n"
    "\n"
    "# B) No usarla: cambiar esos seis servicios en docker-compose.yml a\n"
    "#    image: lscr.io/linuxserver/webtop:ubuntu-mate",
)

# --- 2 ---------------------------------------------------------------------
titulo(doc, "2. Requisitos")

vineta(doc, "**Docker** con Compose (Docker Desktop en Windows, docker-ce en Linux).")
vineta(doc, "**RAM**: cada escritorio Webtop pide unos 1,5 GB. Con 8 escritorios, 16 GB es lo mínimo razonable; si no se usan todos a la vez, Docker solo consume lo que usan.")
vineta(doc, "**Disco**: unos 6 GB por imagen de escritorio, más lo que ocupe cada carpeta de datos.")
vineta(doc, "Un **dominio** con capacidad de túneles (aquí se usa Cloudflare Tunnel).")

# --- 3 ---------------------------------------------------------------------
titulo(doc, "3. Preparar el entorno")

codigo(doc, "cp .env.example .env")

parrafo(doc, "Edita el archivo .env y cambia como mínimo:", tam=10)

codigo(
    doc,
    "APP_ENV=production\n"
    "APP_DEBUG=false\n"
    "APP_URL=https://TU-DOMINIO\n"
    "APP_KEY=\n"
    "\n"
    "# Instalador: clave privada para crear el primer administrador\n"
    "INSTALL_KEY=una-clave-larga-y-aleatoria\n"
    "ADMIN_PASSWORD=algo-que-no-sea-la-del-ejemplo\n"
    "\n"
    "# Base de datos: cambia las tres contraseñas\n"
    "DB_PASSWORD=...\n"
    "DB_ROOT_PASSWORD=...\n"
    "\n"
    "# Dominio de las cookies. DEBE empezar por punto para que la sesión llegue a\n"
    "# los subdominios de los escritorios (ct0.tudominio, ct2.tudominio...)\n"
    "SESSION_DOMAIN=.TU-DOMINIO\n"
    "FORCE_HTTPS=true",
)

parrafo(doc, "Generar APP_KEY", tam=11).runs[0].bold = True
parrafo(
    doc,
    "APP_KEY es una cadena aleatoria de 32 bytes en base64. Esta forma funciona en "
    "cualquier máquina, sin necesidad de tener PHP ni las dependencias instaladas:",
)

codigo(doc, "docker run --rm php:8.4-cli php -r \"echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;\"")

parrafo(
    doc,
    "Copia el resultado en APP_KEY=base64:...  Si ya tienes el proyecto con "
    "dependencias en el host, también vale «php artisan key:generate».",
)

parrafo(doc, "Rutas de los escritorios", tam=11).runs[0].bold = True

codigo(
    doc,
    "# Windows\n"
    "CONTAINER_DATA_DIR=C:\\webtop\n"
    "\n"
    "# Linux\n"
    "CONTAINER_DATA_DIR=/srv/webtop",
)

codigo(
    doc,
    "# Carpeta del escritorio de invitado. Se monta en la app para poder borrar su\n"
    "# información personal al terminar cada sesión de invitado.\n"
    "GUEST_DATA_DIR=C:\\webtop\\ct7      # Windows\n"
    "GUEST_DATA_DIR=/srv/webtop/ct7     # Linux",
)

aviso(
    doc,
    "Ojo en Linux:",
    "El valor por defecto de GUEST_DATA_DIR es C:\\webtop\\ct7. En Linux hay que "
    "cambiarlo o el montaje fallará.",
    color_fondo="EAF3EA",
    color_titulo=VERDE,
)

parrafo(doc, "Escritorios remotos (opcional)", tam=11).runs[0].bold = True
parrafo(
    doc,
    "Si ct8–ct11 viven en otra máquina, edita docker/Caddyfile con su IP real:",
)

codigo(
    doc,
    "import webtop_proxy ct8 192.168.1.100:3000\n"
    "import webtop_proxy ct9 192.168.1.100:3100",
)

parrafo(doc, "Y después, el host de sondeo de esos escritorios en la base de datos:", tam=10)

codigo(
    doc,
    "docker compose -p virthub exec -u www-data app php artisan tinker --execute=\"\n"
    "App\\Models\\WebtopContainer::whereIn('name', ['ct8','ct9','ct10','ct11'])\n"
    "    ->update(['internal_host' => '192.168.1.100']);\n"
    "\"",
)

aviso(
    doc,
    "Fija esa IP por DHCP en el router.",
    "Si cambia, hay que actualizarla en dos sitios: el Caddyfile y la base de datos.",
    color_fondo="EAF3EA",
    color_titulo=VERDE,
)

# --- 4 ---------------------------------------------------------------------
titulo(doc, "4. Construir y levantar")

codigo(doc, "docker compose -p virthub --env-file .env up -d --build")

parrafo(
    doc,
    "La primera vez tarda: descarga las imágenes de escritorio (unos 6 GB cada una).",
)

parrafo(doc, "Las migraciones se ejecutan solas.", tam=10).runs[0].bold = True
parrafo(
    doc,
    "El CMD del contenedor hace «php artisan migrate --force» y «storage:link» antes "
    "de arrancar Apache, así que no hay que lanzarlas a mano.",
)

codigo(doc, "docker compose -p virthub ps")

# --- 5 ---------------------------------------------------------------------
titulo(doc, "5. Crear el administrador")

codigo(doc, "https://TU-DOMINIO/install?key=TU_INSTALL_KEY")

parrafo(
    doc,
    "Sin la clave correcta no se abre nada. INSTALL_ALLOW_LOCALHOST=true solo "
    "habilita la vía sin clave desde el propio servidor.",
)

# --- 6 ---------------------------------------------------------------------
titulo(doc, "6. Registrar los escritorios")

parrafo(
    doc,
    "Cada escritorio debe existir en la base de datos para que el panel lo muestre y "
    "para que el proxy sepa autorizarlo:",
)

codigo(doc, "docker compose -p virthub exec -u www-data app php artisan virthub:sync-containers")

aviso(
    doc,
    "Usa siempre -u www-data.",
    "Ejecutar artisan como root deja archivos de caché con ese dueño y después la "
    "web falla con «Permission denied» en storage.",
)

# --- 7 ---------------------------------------------------------------------
titulo(doc, "7. Exponerlo a internet (Cloudflare Tunnel)")

parrafo(
    doc,
    "Caddy escucha solo en loopback (127.0.0.1:8080) y el túnel corre en el host. "
    "El archivo de configuración del túnel (config.yml) queda así:",
)

codigo(
    doc,
    "tunnel: TU-TUNNEL-ID\n"
    "credentials-file: C:\\Users\\<usuario>\\.cloudflared\\TU-TUNNEL-ID.json\n"
    "\n"
    "ingress:\n"
    "  - hostname: TU-DOMINIO\n"
    "    service: http://127.0.0.1:8080\n"
    "  - hostname: ct0.TU-DOMINIO\n"
    "    service: http://127.0.0.1:8080\n"
    "  - hostname: ct2.TU-DOMINIO\n"
    "    service: http://127.0.0.1:8080\n"
    "  # ... un bloque por cada escritorio hasta ct11\n"
    "  - service: http_status:404",
)

parrafo(
    doc,
    "Cada hostname necesita además su registro DNS (tipo «Túnel», apuntando al "
    "túnel). Sin el registro, el subdominio no resuelve.",
)

codigo(
    doc,
    "cloudflared tunnel ingress validate\n"
    "# reiniciar el servicio para que lea los cambios:\n"
    "# config.yml solo se lee al arrancar",
)

aviso(
    doc,
    "Alternativa sin túnel:",
    "Publica Caddy directamente (ports: \"80:80\" y \"443:443\") y deja que emita los "
    "certificados. En ese caso quita el :8080 de los sitios del Caddyfile.",
    color_fondo="EAF3EA",
    color_titulo=VERDE,
)

# --- 8 ---------------------------------------------------------------------
titulo(doc, "8. Comprobar que funciona")

codigo(
    doc,
    "# La aplicación responde\n"
    "curl -I https://TU-DOMINIO/\n"
    "# -> HTTP 200\n"
    "\n"
    "# Un escritorio responde y está protegido\n"
    "curl -I https://ct0.TU-DOMINIO/\n"
    "# -> HTTP 403  (403 = protegido y en pie; es lo correcto)\n"
    "\n"
    "# Estado interno de los escritorios\n"
    "docker compose -p virthub exec -u www-data app php artisan tinker --execute=\"\n"
    "$s = app(App\\Support\\SystemStatusMonitor::class)->snapshot();\n"
    "foreach ($s['container_status'] as $n => $v) {\n"
    "    echo str_pad($n, 6) . ($v['online'] ? 'en linea' : 'caido') . PHP_EOL;\n"
    "}\"\n".replace('}\"', '}"'),
)

# --- 9 ---------------------------------------------------------------------
titulo(doc, "9. Problemas típicos")

tabla(
    doc,
    ["Síntoma", "Causa", "Solución"],
    [
        ["502 en un escritorio", "Su contenedor está apagado o la IP cambió", "docker compose up -d ct5 / revisar Caddyfile"],
        ["403 al abrir el escritorio", "El pase no vale o el usuario no es dueño", "Recargar desde la página de contenedor"],
        ["404 en /api/websockets", "Selkies no arrancó", "Ver permisos de /config"],
        ["500 Permission denied en storage", "Se ejecutó artisan como root", "docker compose restart app"],
        ["«WebSocket disconnected»", "Dueño incorrecto en /config", "Ver docker/CLONAR-ESCRITORIOS.md"],
        ["El panel marca caídos escritorios activos", "IP del equipo remoto cambiada", "Actualizar Caddyfile e internal_host"],
    ],
    anchos=[5.2, 5.6, 5.2],
)

parrafo(doc, "Ver los registros", tam=11).runs[0].bold = True

codigo(
    doc,
    "docker compose -p virthub logs -f app\n"
    "docker compose -p virthub logs -f caddy",
)

parrafo(doc, "Detener sin perder datos", tam=11).runs[0].bold = True

codigo(
    doc,
    "docker compose -p virthub down       # conserva volúmenes y escritorios\n"
    "docker compose -p virthub down -v    # BORRA la base de datos y storage",
)

parrafo(
    doc,
    "Los escritorios no están en volúmenes Docker: sus datos viven en "
    "CONTAINER_DATA_DIR (C:\\webtop o /srv/webtop) y sobreviven a «down -v».",
)

# --- 10 --------------------------------------------------------------------
titulo(doc, "10. Actualizar una instancia ya desplegada")

codigo(
    doc,
    "git pull\n"
    "docker compose -p virthub --env-file .env up -d --build app caddy\n"
    "docker compose -p virthub exec -u www-data app php artisan virthub:sync-containers",
)

parrafo(doc, "Las migraciones nuevas se aplican solas al recrear el contenedor app.")

# --- 11 --------------------------------------------------------------------
titulo(doc, "11. Resumen de comandos")

codigo(
    doc,
    "# Preparar\n"
    "cp .env.example .env          # y editar claves, dominio y rutas\n"
    "\n"
    "# Levantar todo\n"
    "docker compose -p virthub --env-file .env up -d --build\n"
    "\n"
    "# Crear el administrador\n"
    "#   https://TU-DOMINIO/install?key=TU_INSTALL_KEY\n"
    "\n"
    "# Registrar los escritorios\n"
    "docker compose -p virthub exec -u www-data app php artisan virthub:sync-containers\n"
    "\n"
    "# Ver estado\n"
    "docker compose -p virthub ps\n"
    "docker compose -p virthub logs -f app",
)

doc.save(SALIDA)
print("Documento generado:", SALIDA)
