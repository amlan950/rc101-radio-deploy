FROM php:8.2-cli

# System deps + PHP extensions Laravel/this app needs.
# Both pdo_mysql and pdo_pgsql are installed so the same image works whether
# DB_CONNECTION is mysql (the project's standard target) or pgsql (used on
# hosts, like Render, that only offer managed Postgres) — selected at runtime
# via the DB_CONNECTION env var, no rebuild needed.
RUN apt-get update && apt-get install -y \
        git unzip libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libzip-dev libicu-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_pgsql mbstring gd zip intl bcmath exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

# Install PHP deps (no dev deps, no scripts since artisan isn't safely runnable pre-.env)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN chmod +x docker/entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080
ENTRYPOINT ["docker/entrypoint.sh"]
