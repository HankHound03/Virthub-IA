FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:22-alpine AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js .
RUN npm run build

FROM php:8.4-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath opcache \
    && a2enmod rewrite deflate expires headers \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Los adjuntos subidos NUNCA deben ejecutarse
# ---------------------------------------------------------------------------
# Los archivos de los usuarios viven en storage/app/public, que se publica a
# traves del enlace public/storage. Sin esta proteccion, un archivo .php subido
# al foro se sirve bajo el docroot y Apache lo EJECUTA: cualquier usuario
# registrado podria ejecutar codigo en el servidor.
#
# La validacion por extension (config/virthub.php) no basta: esta es la red de
# seguridad que actua aunque algo se cuele.
RUN printf '%s\n' \
        '<Directory /var/www/html/storage/app/public>' \
        '    # Nada de lo que se sube se interpreta como programa.' \
        '    php_admin_flag engine off' \
        '    Options -ExecCGI -Indexes' \
        '    <FilesMatch "(?i)\.(php|php[0-9]|phtml|phps|phar|inc|cgi|pl|py|sh|htaccess|htpasswd)$">' \
        '        Require all denied' \
        '    </FilesMatch>' \
        '</Directory>' \
        '<Directory /var/www/html/public/storage>' \
        '    php_admin_flag engine off' \
        '    Options -ExecCGI -Indexes' \
        '    <FilesMatch "(?i)\.(php|php[0-9]|phtml|phps|phar|inc|cgi|pl|py|sh|htaccess|htpasswd)$">' \
        '        Require all denied' \
        '    </FilesMatch>' \
        '</Directory>' \
    > /etc/apache2/conf-available/virthub-uploads.conf \
    && a2enconf virthub-uploads

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-virthub.ini

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /var/www/html/public/build ./public/build

RUN rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

RUN mkdir -p storage/app/data storage/app/chunked storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

# Los adjuntos viven en storage/app/public, que es el volumen persistente. Hay
# que crear sus subdirectorios con el propietario correcto ANTES de que corran
# los procesos de arranque como root: si los crea root, www-data no puede
# escribir dentro y subir un avatar o adjunto falla con "Permission denied".
# Se hace en el arranque y no solo en el build porque el volumen puede
# reemplazar el contenido de la imagen.
# ---------------------------------------------------------------------------
# Rendimiento: comprimir y cachear los recursos estaticos
# ---------------------------------------------------------------------------
# Sin esto, el CSS, el JS y los fondos viajan sin comprimir en cada visita: son
# unos 140 KB de texto y 260 KB de imagenes que se pueden reducir mucho.
#
# Los recursos llevan ?v=<fecha>, asi que se pueden cachear para siempre: si
# cambian, cambia la direccion y el navegador pide la nueva.
RUN printf '%s\n' \
        '<IfModule mod_deflate.c>' \
        '    AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml' \
        '    AddOutputFilterByType DEFLATE application/javascript application/json' \
        '    AddOutputFilterByType DEFLATE image/svg+xml' \
        '</IfModule>' \
        '<IfModule mod_expires.c>' \
        '    ExpiresActive On' \
        '    ExpiresByType text/css "access plus 1 year"' \
        '    ExpiresByType application/javascript "access plus 1 year"' \
        '    ExpiresByType image/webp "access plus 1 year"' \
        '    ExpiresByType image/jpeg "access plus 1 year"' \
        '    ExpiresByType image/png "access plus 1 year"' \
        '    ExpiresByType image/svg+xml "access plus 1 year"' \
        '    ExpiresByType font/woff2 "access plus 1 year"' \
        '</IfModule>' \
        '<IfModule mod_headers.c>' \
        '    <FilesMatch "\.(css|js|jpg|jpeg|png|webp|gif|svg|woff2)$">' \
        '        Header set Cache-Control "public, max-age=31536000, immutable"' \
        '    </FilesMatch>' \
        '</IfModule>' \
    > /etc/apache2/conf-available/virthub-rendimiento.conf \
    && a2enconf virthub-rendimiento

CMD ["sh", "-c", "mkdir -p storage/app/data storage/app/chunked storage/app/public/uploads/profiles storage/app/public/uploads/forum/photos storage/app/public/uploads/forum/videos storage/app/public/uploads/forum/files && chown -R www-data:www-data storage && php artisan migrate --force && rm -rf public/storage && php artisan storage:link && apache2-foreground"]

