"""
Utilidades comunes para los manuales de Virthub.

Centraliza el estilo para que los dos manuales (usuario y administrador) se vean
como parte del mismo juego y no como dos documentos distintos.
"""

import os

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor

# Paleta: el verde azulado de Virthub.
VERDE = RGBColor(0x1F, 0x6F, 0x5C)
VERDE_CLARO = RGBColor(0x2E, 0x8B, 0x72)
GRIS = RGBColor(0x55, 0x5F, 0x5F)
GRIS_CLARO = RGBColor(0x8A, 0x93, 0x93)
NEGRO = RGBColor(0x1E, 0x24, 0x24)

FUENTE = "Segoe UI"
FUENTE_TITULO = "Segoe UI Semibold"

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IMAGENES = os.path.join(RAIZ, "docs", "img")


def imagen(nombre: str) -> str:
    """Ruta absoluta de una captura."""
    return os.path.join(IMAGENES, nombre)


# ---------------------------------------------------------------------------
# Documento base
# ---------------------------------------------------------------------------


def nuevo_documento() -> Document:
    doc = Document()

    seccion = doc.sections[0]
    seccion.page_width = Inches(8.5)
    seccion.page_height = Inches(11)
    seccion.top_margin = Inches(0.9)
    seccion.bottom_margin = Inches(0.85)
    seccion.left_margin = Inches(0.95)
    seccion.right_margin = Inches(0.95)

    estilos = doc.styles

    normal = estilos["Normal"]
    normal.font.name = FUENTE
    normal.font.size = Pt(10.5)
    normal.font.color.rgb = NEGRO
    normal.paragraph_format.space_after = Pt(7)
    normal.paragraph_format.line_spacing = 1.18
    _fijar_ea(normal.element, FUENTE)

    for nivel, tam in ((1, 17), (2, 13), (3, 11.5)):
        estilo = estilos[f"Heading {nivel}"]
        estilo.font.name = FUENTE_TITULO
        estilo.font.size = Pt(tam)
        estilo.font.color.rgb = VERDE if nivel == 1 else VERDE_CLARO
        estilo.font.bold = True
        estilo.paragraph_format.space_before = Pt(16 if nivel == 1 else 12)
        estilo.paragraph_format.space_after = Pt(6)
        estilo.paragraph_format.keep_with_next = True

    # Pie de pagina con el nombre del manual y el numero.
    pie = seccion.footer.paragraphs[0]
    pie.alignment = WD_ALIGN_PARAGRAPH.CENTER
    pie_run = pie.add_run("Virthub 2.0")
    pie_run.font.size = Pt(8)
    pie_run.font.color.rgb = GRIS_CLARO
    pie_run.font.name = FUENTE

    return doc


def _fijar_ea(elemento, fuente: str) -> None:
    """python-docx no escribe w:eastAsia; con esto el nombre de fuente se respeta."""
    rpr = elemento.find(qn("w:rPr"))
    if rpr is None:
        return
    rfonts = rpr.find(qn("w:rFonts"))
    if rfonts is None:
        return
    rfonts.set(qn("w:eastAsia"), fuente)
    rfonts.set(qn("w:cs"), fuente)


# ---------------------------------------------------------------------------
# Bloques
# ---------------------------------------------------------------------------


def portada(doc: Document, titulo: str, subtitulo: str, detalle: str, imagen_portada: str | None = None) -> None:
    if imagen_portada and os.path.exists(imagen_portada):
        doc.add_picture(imagen_portada, width=Inches(6.4))
        doc.paragraphs[-1].alignment = WD_ALIGN_PARAGRAPH.CENTER

    for _ in range(2):
        doc.add_paragraph()

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run(titulo)
    run.font.name = FUENTE_TITULO
    run.font.size = Pt(30)
    run.font.bold = True
    run.font.color.rgb = VERDE

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run(subtitulo)
    run.font.name = FUENTE
    run.font.size = Pt(15)
    run.font.color.rgb = GRIS

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(24)
    run = p.add_run(detalle)
    run.font.name = FUENTE
    run.font.size = Pt(10)
    run.font.color.rgb = GRIS_CLARO

    _linea(doc)

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run("Virthub 2.0  ·  Universidad Tecnológica de Tecámac")
    run.font.size = Pt(9)
    run.font.color.rgb = GRIS_CLARO
    run.font.name = FUENTE

    # Cierre de la portada: la frase que resume el proyecto.
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(28)
    run = p.add_run("«De la comunidad para la comunidad»")
    run.font.size = Pt(9.5)
    run.italic = True
    run.font.color.rgb = GRIS
    run.font.name = FUENTE

    doc.add_page_break()


def _linea(doc: Document, color: str = "1F6F5C", grosor: int = 8) -> None:
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after = Pt(10)
    ppr = p._p.get_or_add_pPr()
    bordes = OxmlElement("w:pBdr")
    abajo = OxmlElement("w:bottom")
    abajo.set(qn("w:val"), "single")
    abajo.set(qn("w:sz"), str(grosor))
    abajo.set(qn("w:space"), "1")
    abajo.set(qn("w:color"), color)
    bordes.append(abajo)
    ppr.append(bordes)


def h1(doc: Document, texto: str) -> None:
    doc.add_heading(texto, level=1)


def h2(doc: Document, texto: str) -> None:
    doc.add_heading(texto, level=2)


def h3(doc: Document, texto: str) -> None:
    doc.add_heading(texto, level=3)


def parrafo(doc: Document, texto: str, negrita: bool = False, cursiva: bool = False) -> None:
    p = doc.add_paragraph()
    run = p.add_run(texto)
    run.bold = negrita
    run.italic = cursiva
    run.font.size = Pt(10.5)


def vineta(doc: Document, texto: str, nivel: int = 0) -> None:
    p = doc.add_paragraph(style="List Bullet" if nivel == 0 else "List Bullet 2")
    p.paragraph_format.space_after = Pt(3)
    p.add_run(texto).font.size = Pt(10.5)


def pasos(doc: Document, elementos: list[str]) -> None:
    for i, texto in enumerate(elementos, start=1):
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.left_indent = Inches(0.25)
        numero = p.add_run(f"{i}. ")
        numero.bold = True
        numero.font.color.rgb = VERDE
        numero.font.size = Pt(10.5)
        run = p.add_run(texto)
        run.font.size = Pt(10.5)


def nota(doc: Document, titulo: str, texto: str, tono: str = "info") -> None:
    """Recuadro de aviso: info (verde), atencion (ambar), peligro (rojo)."""
    colores = {
        "info": ("E8F3EF", "1F6F5C"),
        "atencion": ("FDF3E0", "B4771A"),
        "peligro": ("FBE9E7", "B03A2E"),
    }
    fondo, borde = colores.get(tono, colores["info"])

    tabla = doc.add_table(rows=1, cols=1)
    tabla.alignment = WD_TABLE_ALIGNMENT.CENTER
    celda = tabla.cell(0, 0)
    _sombra(celda, fondo)
    _borde(celda, borde)

    p = celda.paragraphs[0]
    p.paragraph_format.space_before = Pt(4)
    p.paragraph_format.space_after = Pt(2)
    run = p.add_run(titulo)
    run.bold = True
    run.font.size = Pt(9.5)
    run.font.color.rgb = RGBColor.from_string(borde)
    run.font.name = FUENTE

    p2 = celda.add_paragraph()
    p2.paragraph_format.space_after = Pt(4)
    run2 = p2.add_run(texto)
    run2.font.size = Pt(9.5)
    run2.font.color.rgb = NEGRO
    run2.font.name = FUENTE

    doc.add_paragraph().paragraph_format.space_after = Pt(2)


def _sombra(celda, color_hex: str) -> None:
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), color_hex)
    celda._tc.get_or_add_tcPr().append(shd)


def _borde(celda, color_hex: str) -> None:
    bordes = OxmlElement("w:tcBorders")
    for lado in ("top", "left", "bottom", "right"):
        b = OxmlElement(f"w:{lado}")
        b.set(qn("w:val"), "single")
        b.set(qn("w:sz"), "6")
        b.set(qn("w:space"), "0")
        b.set(qn("w:color"), color_hex)
        bordes.append(b)
    celda._tc.get_or_add_tcPr().append(bordes)


def tabla(doc: Document, encabezados: list[str], filas: list[list[str]], anchos: list[float] | None = None) -> None:
    t = doc.add_table(rows=1, cols=len(encabezados))
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.CENTER

    cabecera = t.rows[0].cells
    for i, texto in enumerate(encabezados):
        cabecera[i].text = ""
        p = cabecera[i].paragraphs[0]
        run = p.add_run(texto)
        run.bold = True
        run.font.size = Pt(9.5)
        run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
        run.font.name = FUENTE
        _sombra(cabecera[i], "1F6F5C")

    for fila in filas:
        celdas = t.add_row().cells
        for i, valor in enumerate(fila):
            celdas[i].text = ""
            p = celdas[i].paragraphs[0]
            p.paragraph_format.space_after = Pt(2)
            run = p.add_run(valor)
            run.font.size = Pt(9.5)
            run.font.name = FUENTE

    if anchos:
        for fila in t.rows:
            for i, ancho in enumerate(anchos):
                fila.cells[i].width = Inches(ancho)

    doc.add_paragraph().paragraph_format.space_after = Pt(2)


def imagen_con_pie(doc: Document, ruta: str, pie: str, ancho: float = 6.1) -> None:
    if not os.path.exists(ruta):
        return

    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after = Pt(2)
    p.add_run().add_picture(ruta, width=Inches(ancho))

    caption = doc.add_paragraph()
    caption.alignment = WD_ALIGN_PARAGRAPH.CENTER
    caption.paragraph_format.space_after = Pt(12)
    run = caption.add_run(pie)
    run.italic = True
    run.font.size = Pt(8.5)
    run.font.color.rgb = GRIS


def indice(doc: Document, titulo: str, entradas: list[tuple[int, str]]) -> None:
    h1(doc, titulo)
    for nivel, texto in entradas:
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(3)
        p.paragraph_format.left_indent = Inches(0.0 if nivel == 1 else 0.28)
        run = p.add_run(texto)
        run.font.size = Pt(10.5 if nivel == 1 else 10)
        run.bold = nivel == 1
        run.font.color.rgb = VERDE if nivel == 1 else NEGRO
        run.font.name = FUENTE
    doc.add_page_break()


def salto(doc: Document) -> None:
    doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)
