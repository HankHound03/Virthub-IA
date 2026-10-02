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
    && a2enmod rewrite \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

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
CMD ["sh", "-c", "mkdir -p storage/app/data storage/app/chunked storage/app/public/uploads/profiles storage/app/public/uploads/forum/photos storage/app/public/uploads/forum/videos storage/app/public/uploads/forum/files && chown -R www-data:www-data storage && php artisan migrate --force && rm -rf public/storage && php artisan storage:link && apache2-foreground"]