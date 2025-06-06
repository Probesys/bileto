# Deploy in production

This document explains how to install Bileto with a “standard” setup.
[Learn how to install Bileto with Docker.](/docs/administrators/docker.md)

In this documentation, it is expected that you're at ease with managing a webserver with PHP.
Nginx is used as the webserver in this documentation.
Apache should work as well, but it hasn't been tested.

## Installation

### Check the requirements

Check Git is installed:

```console
$ git --version
git version 2.38.1
```

Check PHP version (must be >= 8.2):

```console
$ php --version
PHP 8.2.13 ...
```

Check your database version.
If you use PostgreSQL (must be >= 13):

```console
$ psql --version
psql (PostgreSQL) 14.3
```

Or if you use MariaDB (must be >= 10.6):

```console
$ mariadb --version
mariadb 10.6.22-MariaDB
```

Check [Composer](https://getcomposer.org/) is installed:

```console
$ composer --version
Composer version 2.4.4 2022-10-27 14:39:29
```

### Create the database

Create a dedicated user and database for Bileto.

With PostgreSQL:

```command
# sudo -u postgres psql
postgres=# CREATE DATABASE bileto_production;
postgres=# CREATE USER bileto_user WITH ENCRYPTED PASSWORD 'secret';
postgres=# GRANT ALL PRIVILEGES ON DATABASE bileto_production TO bileto_user;
```

With MariaDB:

```console
# mariadb -u root -p
MariaDB [(none)]> CREATE DATABASE bileto_production;
MariaDB [(none)]> CREATE USER 'bileto_user'@'localhost' IDENTIFIED BY 'secret';
MariaDB [(none)]> GRANT ALL PRIVILEGES ON bileto_production.* TO 'bileto_user'@'localhost';
MariaDB [(none)]> FLUSH PRIVILEGES;
```

### Download the code

You may want to put Bileto under a specific folder on your server, for instance:

```console
$ cd /var/www/
```

Clone the code:

```console
$ git clone https://github.com/Probesys/bileto.git
$ cd bileto
```

If your user doesn't have the permission to write in this folder, execute the command as `root`.

### About file permissions

You’ll have to make sure that the system user that runs the webserver can access the files under the `/var/www/bileto` directory.
This user is often `www-data`, `apache` or `nginx`.
In this documentation, we’ll use `www-data` because it is the most generic name.

Set the owner of the files to the user that runs your webserver:

```console
$ sudo chown -R www-data:www-data .
```

From now on, you must execute the commands as the user `www-data`.
You can either start a shell for this user (to execute as root):

```console
# su www-data -s /bin/bash
www-data$ cd /var/www/bileto
```

Or prefix **all** the commands with `sudo -u www-data`.
For instance:

```console
$ sudo -u www-data php bin/console
```

If your current user is not in the sudoers list, you’ll need to execute the `sudo` commands as `root`.

The commands that need to be executed as `www-data` **will be prefixed by `www-data$` instead of simply `$` in the rest of the documentation.**

### Switch to the latest version

Checkout the code to the latest version of Bileto:

```
www-data$ git switch $(git describe --tags $(git rev-list --tags --max-count=1))
```

Go to GitHub if you want to find [the full list of releases](https://github.com/Probesys/bileto/releases).

### Check the PHP extensions

Check that the PHP extensions are installed:

```console
$ composer check-platform-reqs
Checking platform requirements for packages in the vendor dir
composer-plugin-api  2.3.0      success
composer-runtime-api 2.2.2      success
ext-ctype            8.1.10     success
...
ext-zip              1.19.5     success
php                  8.2.13     success
```

If requirements are not met, you’ll have to install the missing extensions.

### Configure the application

Create a `.env.local` file:

```console
www-data$ cp env.sample .env.local
```

And edit the variables to your needs.
The file is commented to help you to change it.

**Restrict the permissions on this file:**

```console
www-data$ chmod 400 .env.local
```

### Install the dependencies

Install the Composer dependencies:

```console
www-data$ composer install --no-dev --optimize-autoloader
```

You don't need to install the NPM dependencies because the assets are already pre-built for production.

### Setup the database

Initialize the database:

```console
www-data$ php bin/console doctrine:migrations:migrate --no-interaction
www-data$ php bin/console db:seeds:load
```

The seeds provide some default roles: Technician, Salesman and User.
It's not required to load the seeds if you don't want these roles.

### Configure the webserver

Configure your webserver to serve Bileto.
With Nginx:

```nginx
server {
    server_name bileto.example.com;
    root /var/www/bileto/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;

        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/bileto_error.log;
    access_log /var/log/nginx/bileto_access.log;
}
```

Check the configuration is correct:

```console
$ nginx -t
```

And reload Nginx:

```console
$ systemctl reload nginx
```

Open Bileto in your web browser: it should display the login page.

### Setup the Messenger worker

The Messenger worker performs asynchronous jobs.
It's a sort of Cron mechanism on steroids.
We'll use Systemd in this documentation, but note that the only requirement is that a command needs to run in the background.

Create the file `/etc/systemd/system/bileto-worker.service`:

```systemd
[Unit]
Description=The Messenger worker for Bileto

[Service]
ExecStart=php /var/www/bileto/bin/console messenger:consume async scheduler_default --time-limit=3600

User=www-data
Group=www-data

Restart=always
RestartSec=30

[Install]
WantedBy=default.target
```

Enable and start the service:

```console
# systemctl enable bileto-worker
# systemctl start bileto-worker
```

You can find the logs with:

```console
# journalctl -f -u bileto-worker@service
```

### Create your users

You must create your first user with the command line:

```console
www-data$ php bin/console app:users:create --email=user@example.com --password=secret
```

**Important note:** users created with the command line have "super-admin" permissions and can do anything in Bileto.

Then, try to login via the interface, it should work.
You can start using Bileto now.

### Optional: Configure an LDAP server

If you want to authenticate your users against an LDAP server, you’ll need to configure Bileto a bit more.
Open your `.env.local` file, and uncomment the `LDAP_*` variables.
If you don’t find them, copy paste them from the [`env.sample` file](/env.sample).
The comments above the variables should be clear enough to help you.

To test your setup, try to login with a user and monitor the application logs for any related errors.

The LDAP directory is synchronized every 12 hours, but new users can log into Bileto immediately.
If you need to synchronize manually, you can run:

```console
www-data$ php bin/console app:ldap:sync
```

### Optional: Send errors to Sentry

You can configure Bileto to send errors (exceptions and logs) to a Sentry server.
All you need to do is set the `SENTRY_DSN` environment variable to the value that Sentry gives you when you create a new project.

You can also set `SENTRY_SEND_DEFAULT_PII` to `true` to send personally identifiable information (PII) to Sentry (e.g. IP, logged-in user's email, etc.).

> [!CAUTION]
> Sending PII to Sentry is subject to GDPR.
> Don't enable this option unless you're sure you're compliant.
> More information about collected data in [the Sentry documentation](https://docs.sentry.io/platforms/php/guides/symfony/data-management/data-collected/).

## Updating the production environment

**Please always start by checking the migration notes in [the changelog](/CHANGELOG.md) before updating Bileto.**

Remember that commands prefixed by `www-data$` need to be run as the `www-data` user.
[Read more about file permissions.](#about-file-permissions)

Pull the changes with Git:

```console
www-data$ git fetch
```

Switch to the latest version:

```console
www-data$ git switch $(git describe --tags $(git rev-list --tags --max-count=1))
```

Install the new/updated dependencies:

```console
www-data$ composer install --no-dev --optimize-autoloader
```

Execute the migrations:

```console
www-data$ php bin/console doctrine:migrations:migrate --no-interaction
```

Finally, restart the Messenger worker with:

```console
# systemctl restart bileto-worker
```
