#!/bin/bash
# ---------------------------------------------------------------------------
#  Pantalla virtual grande.
#
#  La imagen arranca Xvfb sin "-screen", asi que la pantalla se queda en el
#  tamano por defecto (1280x1024) y no puede crecer: si la ventana del navegador
#  es mayor, Selkies estira la imagen y los iconos y las ventanas se ven enormes.
#
#  Este script, que LinuxServer ejecuta en cada arranque del contenedor, anade la
#  pantalla grande a la orden de Xvfb. El framebuffer de 2560x1440 permite luego
#  cualquier tamano menor (1920x1080, 1600x900, el de la ventana...).
# ---------------------------------------------------------------------------

ARCHIVO=/etc/s6-overlay/s6-rc.d/svc-xorg/run
TAMANO="2560x1440x24"

if [ ! -f "$ARCHIVO" ]; then
    echo "[pantalla-grande] no encuentro $ARCHIVO, nada que hacer"
    exit 0
fi

if grep -q -- "-screen" "$ARCHIVO"; then
    echo "[pantalla-grande] ya tiene pantalla definida, se deja como esta"
    exit 0
fi

# Se anade la pantalla justo antes de cerrar la orden de Xvfb.
sed -i "s|-shmem \\\\|-shmem -screen 0 ${TAMANO} \\\\|" "$ARCHIVO"

if grep -q -- "-screen 0 ${TAMANO}" "$ARCHIVO"; then
    echo "[pantalla-grande] pantalla configurada en ${TAMANO}"
else
    echo "[pantalla-grande] no se pudo modificar $ARCHIVO"
fi
