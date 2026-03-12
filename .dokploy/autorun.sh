#!/bin/sh

cd /var/www/html

echo "Running Symfony startup tasks..."
echo "DATABASE_URL=$DATABASE_URL"

php bin/console cache:clear --no-warmup || echo "cache:clear failed, continuing..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || echo "migrations failed, continuing..."

echo "Symfony startup tasks complete."
