#!/bin/sh
set -e

# Wait for database to be ready
echo "Waiting for database connection..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  echo "Database not ready yet, waiting..."
  sleep 2
done

echo "Database is ready!"

# Run migrations
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || {
    echo "Migrations failed, trying schema update..."
    php bin/console doctrine:schema:update --force
}

# Load fixtures
echo "Loading database fixtures..."
php bin/console doctrine:fixtures:load --no-interaction

echo "Startup complete! Starting PHP-FPM..."
exec php-fpm --allow-to-run-as-root
