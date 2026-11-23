#!/bin/sh
set -eu

PORT="${PORT:-10000}"

case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT must be a valid TCP port number." >&2
        exit 1
        ;;
esac

if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT must be between 1 and 65535." >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

# Garante que cada versão entre no ar com o esquema correspondente. O banco da
# Neon pode estar acordando após um período ocioso, então repetimos algumas vezes
# antes de considerar a inicialização inválida. `migrate` é idempotente e o Render
# executa apenas as migrations ainda pendentes.
migration_attempt=1
while ! php artisan migrate --force; do
    if [ "$migration_attempt" -ge 5 ]; then
        echo "Database migrations failed after ${migration_attempt} attempts." >&2
        exit 1
    fi

    echo "Database is not ready; retrying migrations (${migration_attempt}/5)..." >&2
    migration_attempt=$((migration_attempt + 1))
    sleep 5
done

exec "$@"
