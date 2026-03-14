#!/bin/sh

cd /var/www/html

echo "Running as: $(whoami)"
echo "Running Symfony startup tasks..."

php bin/console cache:clear --no-warmup
php bin/console assets:install
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Symfony startup tasks complete."
