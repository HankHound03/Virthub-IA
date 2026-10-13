#!/bin/sh
# =============================================================================
# Arranque de Caddy con las credenciales de los escritorios ya codificadas
# =============================================================================
#
# El contenedor Webtop puede exigir autenticacion propia (nginx basic auth). En
# lugar de que el navegador la pida --lo que dibujaria un dialogo del sistema
# sobre toda la aplicacion--, la presenta Caddy con la cabecera Authorization.
#
# Esa cabecera necesita las credenciales en base64, y la codificacion se hace
# aqui para que cambiar la contrasena solo requiera editar el .env y recrear el
# contenedor: nadie tiene que codificar nada a mano.
#
# Si no hay contrasena, se envia una cadena vacia y el contenedor no pedira nada.

set -e

if [ -n "${CONTAINER_AUTH_PASSWORD}" ]; then
	CADDY_BASIC_AUTH="Basic $(printf '%s' "${CONTAINER_AUTH_USER}:${CONTAINER_AUTH_PASSWORD}" | base64)"
else
	CADDY_BASIC_AUTH=""
fi

export CADDY_BASIC_AUTH

exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
