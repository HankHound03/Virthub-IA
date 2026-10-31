"""
Genera el Manual de Usuario de Virthub 2.0 (DOCX).
"""

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from _kit_docx import (  # noqa: E402
    imagen,
    imagen_con_pie,
    indice,
    h1,
    h2,
    h3,
    nota,
    nuevo_documento,
    parrafo,
    pasos,
    portada,
    salto,
    tabla,
    vineta,
)

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SALIDA = os.path.join(RAIZ, "docs", "Manual-de-Usuario-Virthub-2.0.docx")


doc = nuevo_documento()

# --------------------------------------------------------------------------
# Portada e indice
# --------------------------------------------------------------------------

portada(
    doc,
    "Manual de Usuario",
    "Virthub 2.0",
    "Guía para estudiantes: foro, chat, perfil y escritorios virtuales\nUniversidad Tecnológica de Tula-Tepeji",
    imagen_portada=imagen("18-inicio-admin.png"),
)

indice(
    doc,
    "Contenido",
    [
        (1, "1. Qué es Virthub"),
        (1, "2. Entrar y salir"),
        (1, "3. La pantalla de inicio"),
        (2, "3.1 Panel de bienvenida y reloj"),
        (2, "3.2 Mini calendario"),
        (2, "3.3 To-do y notas rápidas"),
        (2, "3.4 Estado del sistema"),
        (1, "4. Los temas: el carrusel"),
        (1, "5. El foro"),
        (2, "5.1 Leer y reaccionar"),
        (2, "5.2 Publicar"),
        (2, "5.3 Adjuntos: foto, video, archivo y encuesta"),
        (2, "5.4 Reportar una publicación"),
        (1, "6. Tu perfil"),
        (1, "7. Amigos"),
        (1, "8. El chat"),
        (2, "8.1 Abrir el chat y sus pestañas"),
        (2, "8.2 Escribir, «está escribiendo» y «visto»"),
        (2, "8.3 Contador de mensajes sin leer"),
        (2, "8.4 Sonido y avisos del navegador"),
        (1, "9. El contenedor: tu escritorio virtual"),
        (1, "10. La IA TutorUTT"),
        (1, "11. Tu cuenta y la seguridad"),
        (2, "11.1 Cambiar la contraseña"),
        (2, "11.2 Verificación en dos pasos (2FA)"),
        (1, "12. AWTY"),
        (1, "13. Problemas frecuentes"),
        (1, "14. Detalles que conviene saber"),
    ],
)

# --------------------------------------------------------------------------
# 1. Que es
# --------------------------------------------------------------------------

h1(doc, "1. Qué es Virthub")

parrafo(
    doc,
    "Virthub es el espacio de la comunidad de la UTT para conversar, compartir y trabajar. "
    "Reúne en un solo lugar el foro del grupo, el chat entre compañeros y un escritorio virtual "
    "completo que se abre dentro del navegador.",
)

parrafo(
    doc,
    "Está pensado para funcionar en equipos modestos. Usted puede tener una computadora de hace "
    "diez años y aun así usar un escritorio con programas actuales: el trabajo pesado ocurre en "
    "el servidor, no en su equipo.",
)

h2(doc, "Lo que puede hacer")

vineta(doc, "Leer y publicar en el foro, con fotos, videos, archivos y encuestas.")
vineta(doc, "Chatear con sus compañeros, con aviso de «está escribiendo» y marca de «visto».")
vineta(doc, "Buscar amigos y enviar solicitudes.")
vineta(doc, "Personalizar su perfil con foto y color de marco.")
vineta(doc, "Usar un escritorio Linux con programas de oficina, navegador y editor de código.")
vineta(doc, "Organizar su día con el calendario, la lista de tareas y las notas rápidas.")
vineta(doc, "Elegir el aspecto visual de todo el sitio entre cinco estilos de Windows.")

nota(
    doc,
    "Requisito importante",
    "Necesita una cuenta de la UTT para publicar. Sin cuenta puede leer el foro y entrar como "
    "invitado, pero no escribir.",
)

# --------------------------------------------------------------------------
# 2. Entrar y salir
# --------------------------------------------------------------------------

h1(doc, "2. Entrar y salir")

h2(doc, "Si ya tiene cuenta")

pasos(
    doc,
    [
        "Abra el sitio de Virthub en su navegador.",
        "Pulse «Iniciar sesión», arriba a la derecha.",
        "Escriba su nombre de usuario y su contraseña.",
        "Si su cuenta tiene verificación en dos pasos, escriba el código de seis dígitos de Google Authenticator.",
    ],
)

parrafo(
    doc,
    "Al entrar verá el encabezado con los botones Inicio, Foro, Sugerencias, Contenedor y Admin "
    "(este último solo si es administrador), junto con el botón del tema y su foto de perfil.",
)

h2(doc, "Si entra como invitado")

parrafo(
    doc,
    "El administrador puede generar accesos temporales. Un invitado ve el inicio, el foro y los "
    "anuncios del chat, con un aviso de cuánto tiempo le queda de sesión.",
)

imagen_con_pie(
    doc,
    imagen("01-inicio-invitado.png"),
    "Figura 1. Vista de invitado: sin sesión no aparecen las secciones privadas.",
)

h2(doc, "Salir")

parrafo(
    doc,
    "Pulse su foto de perfil, arriba a la derecha, y elija «Cerrar Sesión». Conviene hacerlo en "
    "computadoras compartidas, sobre todo si entró con el escritorio virtual abierto.",
)

nota(
    doc,
    "Su sesión del escritorio",
    "Cerrar sesión en Virthub no cierra el escritorio virtual si lo dejó abierto en otra pestaña. "
    "Cierre también esa pestaña.",
)

# --------------------------------------------------------------------------
# 3. Inicio
# --------------------------------------------------------------------------

h1(doc, "3. La pantalla de inicio")

parrafo(
    doc,
    "El inicio reúne los llamados «gadgets»: tarjetas que puede mover y cambiar de tamaño. "
    "Cada una se arrastra por el tirador de la esquina y se ajusta con el botón Normal / Ancho.",
)

imagen_con_pie(
    doc,
    imagen("05-inicio-admin-calendario.png"),
    "Figura 2. El inicio con el panel de bienvenida y el mini calendario.",
)

h2(doc, "3.1 Panel de bienvenida y reloj")

parrafo(
    doc,
    "El primer panel le da la bienvenida con su nombre, muestra la hora y la fecha del día y tiene "
    "los accesos a su perfil. Para un invitado, indica cuánto le queda de sesión.",
)

h2(doc, "3.2 Mini calendario")

parrafo(
    doc,
    "Muestra el mes y la fecha de hoy. Al pulsar un día puede escribir los eventos de esa fecha "
    "y guardarlos. Los días con eventos se marcan en un color distinto y con un punto debajo del "
    "número, así se reconocen de un vistazo.",
)

vineta(doc, "Las flechas de la esquina cambian de mes.")
vineta(doc, "Los eventos se guardan en su navegador, no en el servidor: son de ese equipo.")
vineta(doc, "El color del marcador cambia según el tema elegido.")

h2(doc, "3.3 To-do y notas rápidas")

parrafo(
    doc,
    "La lista de tareas le deja anotar pendientes y marcarlos como hechos. Las notas rápidas son "
    "un cuaderno corto para ideas y recordatorios.",
)

imagen_con_pie(
    doc,
    imagen("16-todo-notas.png"),
    "Figura 3. Lista de tareas y notas rápidas.",
)

nota(
    doc,
    "Se guardan en su equipo",
    "Tareas, notas y eventos del calendario viven en el navegador de esa computadora. Si usa otra "
    "máquina o borra los datos del navegador, no estarán ahí. Para trabajos importantes use el "
    "escritorio virtual o el foro.",
)

h2(doc, "3.4 Estado del sistema")

parrafo(
    doc,
    "Muestra de un vistazo cómo va el equipo: procesador, memoria, disco y el estado de los "
    "contenedores del servidor. Si algo está en rojo, avise al administrador.",
)

imagen_con_pie(
    doc,
    imagen("06-gadgets-estado.png"),
    "Figura 4. Estado del sistema: CPU, RAM, disco y contenedores.",
)

# --------------------------------------------------------------------------
# 4. Temas
# --------------------------------------------------------------------------

h1(doc, "4. Los temas: el carrusel")

parrafo(
    doc,
    "Virthub puede verse como cinco versiones de Windows: XP, Vista, 7, 8 y 10. Cada una tiene "
    "seis esquemas de color, treinta en total. El botón del tema está arriba a la derecha, junto "
    "a su perfil.",
)

imagen_con_pie(
    doc,
    imagen("04-temas-carrusel.png"),
    "Figura 5. Panel de temas: una fila deslizable por cada época de Windows.",
    ancho=5.2,
)

pasos(
    doc,
    [
        "Pulse el botón del tema (muestra el estilo actual, por ejemplo «Windows 7 · Escenas»).",
        "Elija la época con la fila correspondiente y deslícela para ver sus esquemas.",
        "Pulse el esquema que quiera y se aplicará al instante.",
    ],
)

parrafo(
    doc,
    "También puede cambiar de tema sin abrir el panel: pulse Alt y las flechas del teclado. El "
    "tema se recuerda en ese navegador.",
)

nota(
    doc,
    "Equipos modestos",
    "Si su equipo es antiguo, Virthub lo detecta solo y apaga los efectos más pesados (desenfoques "
    "y fondos fijos). No hace falta configurar nada: sigue siendo cómodo de usar.",
)

# --------------------------------------------------------------------------
# 5. Foro
# --------------------------------------------------------------------------

h1(doc, "5. El foro")

parrafo(
    doc,
    "El foro es el espacio común del grupo. Cualquiera con cuenta puede publicar; los invitados "
    "solo leen.",
)

h2(doc, "5.1 Leer y reaccionar")

parrafo(
    doc,
    "Cada publicación muestra quién la escribió, la fecha y el contenido. Puede reaccionar a las "
    "publicaciones y responder dentro de ellas.",
)

imagen_con_pie(
    doc,
    imagen("03-foro-video.png"),
    "Figura 6. Publicación del foro con un video adjunto.",
)

h2(doc, "5.2 Publicar")

pasos(
    doc,
    [
        "Pulse «Publicar» en la parte superior del foro.",
        "Escriba un título breve (opcional) y el contenido.",
        "Si quiere adjuntar algo, pulse Foto, Encuesta, Video o Archivo.",
        "Pulse «Publicar» para enviarlo.",
    ],
)

imagen_con_pie(
    doc,
    imagen("17-crear-publicacion.png"),
    "Figura 7. Ventana para crear una publicación.",
    ancho=5.4,
)

h2(doc, "5.3 Adjuntos: foto, video, archivo y encuesta")

tabla(
    doc,
    ["Tipo", "Para qué sirve", "Detalles"],
    [
        ["Foto", "Imágenes del grupo o de un trabajo.", "Se revisan automáticamente antes de publicarse."],
        ["Video", "Grabaciones o clips.", "Se reproduce dentro del foro."],
        ["Archivo", "Documentos, prácticas, apuntes.", "Hasta 5 GB por archivo."],
        ["Encuesta", "Preguntar algo al grupo.", "Votación dentro de la publicación."],
    ],
    anchos=[1.1, 2.6, 2.9],
)

parrafo(
    doc,
    "Los archivos grandes se suben por partes: si se corta la conexión, el avance se conserva y "
    "puede reanudarse. El botón «Cancelar» detiene la subida sin dejar restos.",
)

h2(doc, "5.4 Reportar una publicación")

parrafo(
    doc,
    "Si algo no debería estar ahí, use «Reportar» en la publicación. El administrador lo revisa. "
    "Los reportes no son anónimos para el administrador, pero sí discretos.",
)

nota(
    doc,
    "Moderación automática",
    "Las fotos pasan por un filtro de contenido no apto. Si su imagen se rechaza, verá un aviso "
    "explicando el motivo. No es un castigo: es una revisión automática y puede equivocarse. Si "
    "cree que se equivocó, avise al administrador.",
)

# --------------------------------------------------------------------------
# 6. Perfil
# --------------------------------------------------------------------------

h1(doc, "6. Tu perfil")

parrafo(
    doc,
    "En «Mi Perfil» puede cambiar su foto, el color del marco estilo Aero y ver sus publicaciones.",
)

imagen_con_pie(
    doc,
    imagen("10-perfil.png"),
    "Figura 8. Perfil: foto, marco Aero y publicaciones.",
    ancho=5.6,
)

pasos(
    doc,
    [
        "Pulse su foto y luego «Mi Perfil».",
        "Para cambiar la foto, pulse «Seleccionar archivo» y suba una imagen.",
        "Para el marco, elija un color en la barra.",
        "Pulse «Guardar perfil».",
    ],
)

parrafo(
    doc,
    "Desde el propio perfil puede publicar algo suyo, como una nota o un trabajo. Esas "
    "publicaciones aparecen en su muro personal.",
)

imagen_con_pie(
    doc,
    imagen("19-perfil-publicaciones.png"),
    "Figura 9. Publicaciones en el muro personal.",
    ancho=5.6,
)

# --------------------------------------------------------------------------
# 7. Amigos
# --------------------------------------------------------------------------

h1(doc, "7. Amigos")

parrafo(
    doc,
    "El chat funciona entre amigos. Para poder escribirle a alguien, primero deben agregarse.",
)

pasos(
    doc,
    [
        "Pulse el icono de búsqueda del encabezado o vaya a «Buscar amigos».",
        "Escriba el nombre, el ID o el nombre de usuario de esa persona.",
        "Pulse «Buscar» y luego «Agregar» en el resultado.",
        "Cuando la otra persona acepte, podrá escribirle por el chat.",
    ],
)

imagen_con_pie(
    doc,
    imagen("09-buscar-amigos.png"),
    "Figura 10. Buscador de amigos.",
    ancho=5.6,
)

parrafo(
    doc,
    "Las solicitudes que le lleguen aparecen en la pestaña «Solicitudes» del chat, donde puede "
    "aceptarlas o rechazarlas.",
)

# --------------------------------------------------------------------------
# 8. Chat
# --------------------------------------------------------------------------

h1(doc, "8. El chat")

h2(doc, "8.1 Abrir el chat y sus pestañas")

parrafo(
    doc,
    "El chat se abre con el botón redondo de la esquina inferior derecha y aparece en cualquier "
    "página del sitio. Al abrirlo se muestra la lista de usuarios, que es lo primero que se "
    "necesita: elegir con quién hablar.",
)

imagen_con_pie(
    doc,
    imagen("12-contenedor-chat.png"),
    "Figura 11. El chat abierto sobre el contenedor, con la lista de contactos.",
    ancho=6.0,
)

tabla(
    doc,
    ["Pestaña", "Qué contiene"],
    [
        ["Mensajes", "La conversación abierta."],
        ["Usuarios", "Sus amigos y su estado de conexión."],
        ["Solicitudes", "Peticiones de amistad por responder."],
        ["IA", "El asistente TutorUTT (solo para el administrador)."],
        ["Anuncios", "Mensajes para todo el grupo."],
    ],
    anchos=[1.3, 5.3],
)

h2(doc, "8.2 Escribir, «está escribiendo» y «visto»")

parrafo(
    doc,
    "Escriba en la caja de abajo y pulse Enter o «Enviar». Mientras usted teclea, la otra persona "
    "ve «está escribiendo…» con tres puntos, que desaparece a los pocos segundos si deja de "
    "escribir.",
)

parrafo(
    doc,
    "Cuando el otro abre la conversación, sus mensajes muestran «✓ Visto». La marca solo aparece "
    "si de verdad abrió la conversación: no se marca por tener el chat abierto de fondo.",
)

h2(doc, "8.3 Contador de mensajes sin leer")

parrafo(
    doc,
    "En la lista de usuarios aparece un número rojo junto al nombre: son los mensajes que esa "
    "persona le mandó y que todavía no ha abierto. El contador desaparece al abrir la conversación.",
)

h2(doc, "8.4 Sonido y avisos del navegador")

parrafo(
    doc,
    "Virthub avisa con un sonido cuando llega un mensaje. Si está mirando esa misma conversación, "
    "suena pero no salta la notificación del navegador ni sube el contador: sería avisar de algo "
    "que ya está viendo.",
)

vineta(doc, "La primera vez, el navegador pide permiso para mostrar notificaciones. Acepte si las quiere.")
vineta(doc, "El sonido no se reproduce hasta que pulse algo en la página: es una regla de los navegadores.")
vineta(doc, "Si cambia a otra pestaña del navegador, el aviso sí salta: no está viendo la conversación.")

# --------------------------------------------------------------------------
# 9. Contenedor
# --------------------------------------------------------------------------

h1(doc, "9. El contenedor: tu escritorio virtual")

parrafo(
    doc,
    "El contenedor es un escritorio Linux completo que se abre dentro del navegador. Lleva "
    "programas de oficina, navegador y herramientas de desarrollo. Su equipo solo dibuja la "
    "pantalla: el trabajo ocurre en el servidor de la universidad.",
)

imagen_con_pie(
    doc,
    imagen("14-escritorio-webtop.png"),
    "Figura 12. Escritorio virtual dentro de Virthub.",
)

pasos(
    doc,
    [
        "Pulse «Contenedor» en el encabezado.",
        "El escritorio se carga en el mismo espacio. La primera vez tarda unos segundos.",
        "Trabaje normalmente: los programas se abren dentro de esa ventana.",
        "Si algo se queda colgado, pulse «Recargar Contenedor».",
    ],
)

parrafo(
    doc,
    "El botón «Ventana Nueva» abre el escritorio en una pestaña aparte, a pantalla completa. Es "
    "más cómodo para trabajar largo rato.",
)

imagen_con_pie(
    doc,
    imagen("15-ventana-nueva.png"),
    "Figura 13. El escritorio en una ventana aparte.",
    ancho=6.0,
)

nota(
    doc,
    "Guarde su trabajo",
    "El escritorio es temporal. Guarde sus archivos en la carpeta personal del escritorio virtual "
    "o descárguelos a su equipo antes de cerrar la sesión. No guarde lo importante solo en el "
    "escritorio de la ventana.",
    tono="atencion",
)

# --------------------------------------------------------------------------
# 10. IA
# --------------------------------------------------------------------------

h1(doc, "10. La IA TutorUTT")

parrafo(
    doc,
    "La pestaña IA del chat abre a TutorUTT, un asistente entrenado para ayudar con las materias "
    "de la carrera: explica temas, propone ejercicios y ordena los pasos de un problema.",
)

imagen_con_pie(
    doc,
    imagen("13-ia-tutorutt.png"),
    "Figura 14. TutorUTT respondiendo dentro del chat.",
    ancho=3.2,
)

parrafo(
    doc,
    "El modelo corre en el equipo del administrador, no en internet: sus preguntas no salen de la "
    "universidad. Puede tardar varios segundos en contestar la primera vez, porque carga el modelo "
    "en memoria.",
)

nota(
    doc,
    "Por ahora es solo del administrador",
    "La IA está habilitada para la cuenta de administrador mientras se ajusta. Si le aparece la "
    "pestaña y no responde, es por eso.",
)

# --------------------------------------------------------------------------
# 11. Cuenta y seguridad
# --------------------------------------------------------------------------

h1(doc, "11. Tu cuenta y la seguridad")

h2(doc, "11.1 Cambiar la contraseña")

pasos(
    doc,
    [
        "Pulse su foto y elija «Configuracion».",
        "En «Seguridad», escriba su contraseña actual y la nueva dos veces.",
        "Pulse «Cambiar mi contraseña».",
    ],
)

h2(doc, "11.2 Verificación en dos pasos (2FA)")

parrafo(
    doc,
    "La verificación en dos pasos añade un código de seis dígitos que cambia cada treinta "
    "segundos. Aunque alguien descubra su contraseña, sin ese código no puede entrar.",
)

imagen_con_pie(
    doc,
    imagen("11-configuracion-2fa.png"),
    "Figura 15. Configuración: seguridad y verificación en dos pasos.",
    ancho=5.8,
)

pasos(
    doc,
    [
        "Instale Google Authenticator en su teléfono.",
        "En Configuración, pulse «Generar nuevos códigos» y escanee el código que aparece.",
        "Guarde los códigos de respaldo en un lugar seguro.",
        "A partir de ahí, al iniciar sesión le pedirá el código de seis dígitos.",
    ],
)

nota(
    doc,
    "Si pierde el teléfono",
    "Use uno de los códigos de respaldo. Si tampoco los tiene, avise al administrador: es el único "
    "que puede devolverle el acceso.",
    tono="atencion",
)

# --------------------------------------------------------------------------
# 12. AWTY
# --------------------------------------------------------------------------

h1(doc, "12. AWTY")

parrafo(
    doc,
    "En el panel de bienvenida del inicio hay un botón pequeño que dice AWTY. Abre una ventana con "
    "un mensaje escrito por quien construyó este proyecto, y con una canción que empieza sola.",
)

parrafo(
    doc,
    "AWTY significa «are we there yet?»: ¿ya estamos ahí? Es una pregunta que se hace en los "
    "momentos en los que el camino se siente largo. Si llegó hasta aquí, léalo.",
)

# --------------------------------------------------------------------------
# 13. Problemas
# --------------------------------------------------------------------------

h1(doc, "13. Problemas frecuentes")

tabla(
    doc,
    ["Síntoma", "Qué hacer"],
    [
        ["No aparece el botón Contenedor", "Su cuenta no tiene escritorio asignado. Avise al administrador."],
        ["El escritorio dice 403 o no carga", "Cierre la pestaña y vuelva a entrar desde «Contenedor». Si sigue, avise al administrador."],
        ["No suena el aviso del chat", "Pulse una vez en la página; los navegadores no dejan sonar sin interacción."],
        ["No puedo escribirle a alguien", "Deben ser amigos. Búsquelo y envíe solicitud."],
        ["Mi foto fue rechazada", "El filtro de contenido la marcó. Pruebe otra imagen o avise al administrador."],
        ["Se ven mal los colores", "Cambie de tema con el carrusel o pulse Ctrl + F5 para recargar los estilos."],
        ["Perdí mis notas o tareas", "Se guardan en el navegador de ese equipo; en otra computadora no aparecen."],
        ["La IA no responde", "Por ahora solo está habilitada para el administrador."],
    ],
    anchos=[2.4, 4.2],
)

# --------------------------------------------------------------------------
# 14. Detalles
# --------------------------------------------------------------------------

h1(doc, "14. Detalles que conviene saber")

vineta(doc, "El sitio recuerda su tema y su último estado en ese navegador, no en su cuenta.")
vineta(doc, "Los gadgets se pueden arrastrar y ensanchar; el botón dice Normal o Ancho.")
vineta(doc, "El foro avisa cuando una publicación suya fue retirada y por qué.")
vineta(doc, "Los archivos del foro se pueden descargar con su nombre original.")
vineta(doc, "El chat consulta mensajes nuevos cada cinco segundos: no hace falta recargar.")
vineta(doc, "Si deja la pestaña en segundo plano, el chat deja de consultar para no gastar batería.")

parrafo(
    doc,
    "Virthub se hizo pensando en estudiantes que necesitan herramientas potentes sin tener que "
    "cambiar de computadora. Si encuentra algo que no funciona o que se puede mejorar, dígalo: "
    "este proyecto se construye entre todos.",
)

doc.save(SALIDA)
print(f"Guardado: {SALIDA}")

# ---------------------------------------------------------------------------
# Verificacion
# ---------------------------------------------------------------------------

from docx import Document  # noqa: E402

comprobado = Document(SALIDA)
parrafos = len(comprobado.paragraphs)
tablas = len(comprobado.tables)
imagenes_doc = len(comprobado.inline_shapes)

print(f"  parrafos: {parrafos}")
print(f"  tablas: {tablas}")
print(f"  imagenes: {imagenes_doc}")
print(f"  titulos: {sum(1 for p in comprobado.paragraphs if p.style.name.startswith('Heading'))}")
