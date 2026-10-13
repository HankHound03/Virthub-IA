#!/bin/sh
# =============================================================================
# Quita la autenticacion basica del escritorio
# =============================================================================
#
# El script de arranque de la imagen hace, cuando hay contrasena:
#
#   printf '%s:%s\n' "$CUSER" "$(openssl passwd -apr1 "$PASSWORD")" > /etc/nginx/.htpasswd
#   sed -i 's/#//g' ${NGINX_CONFIG}     # <- descomenta TODO el archivo
#
# Ese 'sed' deja auth_basic activo en /etc/nginx/sites-available/default, y NO se
# revierte al quitar la contrasena: sin PASSWORD el script no vuelve a tocar el
# archivo. Por eso el escritorio seguia pidiendo credenciales.
#
# Aqui se restaura el archivo desde la plantilla de la imagen
# (/defaults/default.conf), que lo tiene comentado, y se elimina el .htpasswd.
#
# Este cambio vive en la capa escribible del contenedor: sobrevive a un
# 'docker restart', pero NO a un 'docker compose up --force-recreate'. Para que
# no vuelva a pasar, no definas CONTAINER_AUTH_PASSWORD en el .env.

set -e

NGINX_CONFIG=/etc/nginx/sites-available/default
PLANTILLA=/defaults/default.conf

echo "== Quitando autenticacion basica del escritorio =="

if [ -f "$PLANTILLA" ]; then
	cp "$PLANTILLA" "$NGINX_CONFIG"
	echo "  config restaurada desde la plantilla"
else
	# Sin plantilla, se comentan las directivas a mano.
	sed -i \
		-e 's:^\(\s*\)auth_basic :\1#auth_basic :' \
		-e 's:^\(\s*\)auth_basic_user_file :\1#auth_basic_user_file :' \
		"$NGINX_CONFIG"
	echo "  directivas comentadas a mano (no habia plantilla)"
fi

rm -f /etc/nginx/.htpasswd
echo "  .htpasswd eliminado"

echo "== Directivas de autenticacion que quedan activas =="
grep -n 'auth_basic' "$NGINX_CONFIG" || echo "  (ninguna)"

# nginx relee su configuracion sin cortar el servicio.
if nginx -s reload 2>/dev/null; then
	echo "== nginx recargado =="
else
	echo "== aviso: nginx no pudo recargar (revisar a mano) =="
fi
