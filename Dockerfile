FROM php:8.2-fpm

# System deps + PHP extensions Laravel/this app needs, plus nginx + supervisor
# to run a real production web server stack (php artisan serve is single-
# threaded and drops requests under concurrent load — not safe for real
# traffic, even for a demo). Both pdo_mysql and pdo_pgsql are installed so the
# same image works whether DB_CONNECTION is mysql (the project's standard
# target) or pgsql (used on hosts, like Render, that only offer managed
# Postgres) — selected at runtime via the DB_CONNECTION env var.
RUN apt-get update && apt-get install -y \
        git unzip nginx supervisor gettext-base \
        libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libzip-dev libicu-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_pgsql mbstring gd zip intl bcmath exif opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

RUN chmod +x docker/entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080
ENTRYPOINT ["docker/entrypoint.sh"]
