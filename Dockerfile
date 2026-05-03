FROM php:8.4-cli-alpine AS base

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --no-cache bash curl freetype-dev git icu-dev libjpeg-turbo-dev libpng-dev libxml2-dev libzip-dev nodejs npm oniguruma-dev postgresql-dev unzip zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install dom gd intl mbstring opcache pdo_pgsql simplexml xml xmlreader xmlwriter zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

FROM base AS production

COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY package.json package-lock.json tsconfig.json vite.config.ts ./
RUN npm ci

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && npm run build \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
