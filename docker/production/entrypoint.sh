#!/bin/sh
set -e

rm -f /tmp/healthy.txt

mkdir -p var/data
mkdir -p var/settings
mkdir -p var/uploads

# Make sure to migrate the application
php bin/console cache:clear
php bin/console doctrine:migration:migrate --no-interaction
php bin/console db:seeds:load

# Configure the trusted proxies to make sure to get the real client IP
# TODO how to run this without being root? :)
if [ "$TRUSTED_PROXY" = "" ]; then
    # Comment the RemoteIPInternalProxy directive
    sed -i "s/RemoteIPInternalProxy/\t\t# RemoteIPInternalProxy/" /etc/apache2/sites-available/000-default.conf
else
    # Set custom list for RemoteIPInternalProxy
    sed -i "s/RemoteIPInternalProxy/\t\tRemoteIPInternalProxy $TRUSTED_PROXIES/" /etc/apache2/sites-available/000-default.conf
fi

touch /tmp/healthy.txt

exec "$@"
