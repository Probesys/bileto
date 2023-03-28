# Updating the production environment

**Please always start by checking the migration notes in [the changelog](/CHANGELOG.md) before updating Bileto.**

Pull the changes with Git:

```console
$ sudo -u www-data git fetch
```

Checkout to the latest version:

```console
$ sudo -u www-data git checkout $(sudo -u www-data git describe --tags $(sudo -u www-data git rev-list --tags --max-count=1))
```

Install the new/updated dependencies:

```console
$ sudo -u www-data composer install --no-dev --optimize-autoloader
```

**While Bileto is not ready for the production yet, you must reset the database.**

With PostgreSQL:

```command
# sudo -u postgres psql
postgres=# DROP DATABASE bileto_production;
postgres=# CREATE DATABASE bileto_production;
postgres=# GRANT ALL PRIVILEGES ON DATABASE bileto_production TO bileto_user;
```

With MariaDB:

```console
# mariadb -u root -p
MariaDB [(none)]> DROP DATABASE bileto_production;
MariaDB [(none)]> CREATE DATABASE bileto_production;
MariaDB [(none)]> GRANT ALL PRIVILEGES ON bileto_production.* TO 'bileto_user'@'localhost';
MariaDB [(none)]> FLUSH PRIVILEGES;
```

Then, initialize the database:

```console
$ sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction
$ sudo -u www-data php bin/console db:seeds:load
```

Then, recreate your super-admin user:

```console
$ sudo -u www-data php bin/console app:users:create --email=user@example.com --password=secret
```

**In the future,** you’ll just have to execute the migrations:

```console
$ sudo -u www-data php bin/console doctrine:migrations:migrate --no-interaction
```
