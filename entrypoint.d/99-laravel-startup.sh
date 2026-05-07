#!/bin/sh
set -e

ARTISAN="/var/www/html/artisan"

if [ ! -f "$ARTISAN" ]; then
    echo "❌ artisan not found at $ARTISAN"
    exit 1
fi

# Generate APP_KEY if not set or empty
if [ -z "$APP_KEY" ]; then
    echo "🔑 APP_KEY not set — generating one..."
    php "$ARTISAN" key:generate --force
else
    echo "✅ APP_KEY is set"
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    SQLITE_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"

    if [ ! -f "$SQLITE_DATABASE" ]; then
        mkdir -p "$(dirname "$SQLITE_DATABASE")"
        touch "$SQLITE_DATABASE"
    fi
fi

# Rebuild config, route, and view caches
echo "⚡ Caching config, routes, and views..."
php "$ARTISAN" config:cache
php "$ARTISAN" route:cache
php "$ARTISAN" view:cache

# Run migrations automatically
echo "🗄️  Running migrations..."
php "$ARTISAN" migrate --force

# Create storage symlink if missing
if [ ! -L "/var/www/html/public/storage" ]; then
    echo "🔗 Creating storage symlink..."
    php "$ARTISAN" storage:link
fi

exit 0
