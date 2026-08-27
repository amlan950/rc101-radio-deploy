#!/bin/sh
set -e

cd /app

# Render (and similar PaaS hosts) inject the assigned public URL at runtime;
# use it as APP_URL when the operator hasn't pinned a custom domain already.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi

# Storage/cache dirs (in case the volume mount wiped permissions)
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Wait for the database to accept connections before migrating (a fresh DB
# plugin/add-on can take a few seconds to come up on first deploy).
if [ -n "$DB_HOST" ]; then
  echo "Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
  for i in $(seq 1 30); do
    php -r "exit(@fsockopen(getenv('DB_HOST'), getenv('DB_PORT') ?: 3306) ? 0 : 1);" && break
    sleep 2
  done
fi

php artisan config:clear || true
php artisan migrate --force
php artisan db:seed --force || true
php artisan storage:link || true

# Render real production traffic through nginx + php-fpm (managed by
# supervisord) instead of `php artisan serve` — the latter is Laravel's
# single-threaded development server and silently drops requests under any
# concurrent load (e.g. a platform health check landing alongside a real
# request), which is not acceptable even for a demo deployment.
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
