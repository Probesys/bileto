#!/bin/sh
set -e

rm -f /tmp/healthy.txt

# Make sure to migrate the application
php bin/console cache:clear
php bin/console doctrine:migration:migrate --no-interaction
php bin/console db:seeds:load

touch /tmp/healthy.txt

exec "$@"
