# Deploy in production

In this documentation, it is expected that you're at ease with managing a webserver with PHP.
Nginx is used as the webserver in this documentation.
Apache should work as well, but it hasn't been tested.

**Warning:** Bileto is not ready for the production yet.
You’ll probably lose your data during an upgrade.

## Check the requirements

Check Git is installed:

```console
$ git --version
git version 2.38.1
```

Check PHP version (must be >= 8.1):

```console
$ php --version
PHP 8.1.12 ...
```

Check your database version.
If you use PostgreSQL (must be >= 14):

```console
$ psql --version
psql (PostgreSQL) 14.3
```

Or if you use MariaDB (must be >= 10.6):

```console
$ mariadb --version
mariadb 10.6.11-MariaDB
```

Check [Composer](https://getcomposer.org/) is installed:

```console
$ composer --version
Composer version 2.4.4 2022-10-27 14:39:29
```

Check the following PHP extensions are installed:

- ctype
- iconv
- intl
- PDO + pdo\_pgsql or pdo\_mysql (depending on which database you use)
- zip

```console
$ php -m
[PHP Modules]
...
```

## Create the database

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

## Download the code

You may want to put Bileto under a specific folder on your server, for instance:

```console
$ cd /var/www/
```

In this case, check that your user has the permissions to write in this folder (or execute the next commands as `root`).

Clone the code:

```console
$ git clone https://github.com/Probesys/bileto.git
$ cd bileto
$ git checkout $(git describe --abbrev=0 --tags)
```

The last command makes sure you're using the latest version of Bileto.
Go to GitHub for [the full list of releases](https://github.com/Probesys/bileto/releases).

## Configure the application

Create a `.env.local` file:

```console
$ cp env.sample .env.local
```

And edit the variables to your needs.
The file is commented to help you to change it.

**Restrict the permissions on this file:**

```console
$ chmod 400 .env.local
```

## Set files permissions

Then, you should set the owner of the files to the user that runs your webserver (this is often `www-data`, `apache` or `nginx`):

```console
$ sudo chown -R www-data:www-data .
```

From now on, it is highly recommended to execute the commands as the user `www-data` (e.g. `sudo -u www-data`).
If your current user is not in the sudoers list, you may need to execute the `sudo` commands as `root`.

## Install the dependencies

Install the Composer dependencies:

```console
$ sudo -u www-data composer install --no-dev --optimize-autoloader
```

You don't need to install the NPM dependencies because the assets are already pre-built for production.

## Setup the database

Initialize the database:

```console
$ sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction
$ sudo -u www-data php bin/console db:seeds:load
```

## Configure the webserver

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
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
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

## Create your users

You must create your first user with the command line:

```console
$ sudo -u www-data php bin/console app:users:create --email=user@example.com --password=secret
```

**Important note:** users created with the command line have "super-admin" permissions and can do anything in Bileto.

Then, try to login via the interface, it should work.
You can start using Bileto now.
