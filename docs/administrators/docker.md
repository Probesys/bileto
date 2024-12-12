# Deploy in production with Docker

This document explains how to install Bileto with Docker.
[Learn how to install Bileto with a “standard” setup.](/docs/administrators/deploy.md)

In this documentation, it is expected that you're at ease with Docker.
We only provide instructions for a basic setup.
You will need to adapt the instructions to your needs, for instance to setup a reverse proxy, or reuse an existing database.

## Installation

### Install Docker

First, you must install Docker.
[Read how to install Docker.](https://docs.docker.com/engine/install/)

Make sure that you also have Docker Compose v2 installed:

```console
$ docker --version
Docker version 24.0.6, build ed223bc
$ docker compose version
Docker Compose version v2.21.0
```

### Choose an image tag

At the moment, we provide two kind of Docker image tags:

- versions tags (e.g. `0.5.0-alpha`), corresponding to [the Bileto releases](https://github.com/Probesys/bileto/releases);
- the `edge` tag, corresponding to the `main` Git branch.

**In most cases, you should select a version tag corresponding to a specific release.**
The `edge` tag can be used if you like to live dangerously, or if you want to try out the latest features of Bileto.

We don't provide a `latest` tag yet.

[See the list of tagged images.](https://github.com/Probesys/bileto/pkgs/container/bileto)

### Create the Docker Compose environment

Create a folder with two files:

```console
$ mkdir bileto
$ cd bileto
$ touch docker-compose.yml
$ touch .env.app
```

Add the following YAML in your `docker-compose.yml`.
You'll need to adapt its content.

```yml
services:
    bileto:
        image: ghcr.io/probesys/bileto:0.5.0-alpha
        restart: unless-stopped
        ports:
            - "8000:80"
        volumes:
            - var:/var/www/html/var
        env_file:
            - .env.app
        depends_on:
            database:
                condition: service_healthy
        healthcheck:
            test: ["CMD", "test", "-f", "/tmp/healthy.txt"]
            interval: 5s
            retries: 30

    worker:
        image: ghcr.io/probesys/bileto:0.5.0-alpha
        restart: unless-stopped
        command: php bin/console messenger:consume async scheduler_default -vv
        entrypoint: ""
        volumes:
            - var:/var/www/html/var
        env_file:
            - .env.app
        depends_on:
            database:
                condition: service_healthy
            bileto:
                condition: service_healthy

    database:
        image: mariadb:10.11
        restart: unless-stopped
        environment:
            - MARIADB_ROOT_PASSWORD=secret
            - MARIADB_DATABASE=bileto
        volumes:
            - database:/var/lib/mysql
        healthcheck:
            test: ["CMD", "/usr/local/bin/healthcheck.sh", "--su-mysql", "--connect", "--innodb_initialized"]
            interval: 5s
            retries: 30

volumes:
    database:
    var:
```

Then, copy the content of the [`env.sample` file](/env.sample) in the `.env.app` file.
In particular, you'll need to adapt the following variables:

```dotenv
APP_SECRET=change-me
DATABASE_URL="mysql://root:secret@database:3306/bileto?serverVersion=10.11.5-MariaDB"
MAILER_DSN=smtp://user:pass@mail.example.com:465
MAILER_FROM=support@example.com
```

### Start the Docker containers

Then, you have to start the containers:

```console
$ docker compose up
```

If everything is correctly configured, the database will start first, then Bileto, and finally the worker container.

Migrations are automatically executed when starting the Bileto container.
They are performed in the entrypoint (it is reset for the worker container to avoid executing the migrations twice).
[Learn how the Docker images work.](/docs/developers/docker-images.md)

You should be able to access Bileto at `your.ip.address:8000`.

### Create a user

You must create your first user with the command line:

```console
$ docker compose exec bileto php bin/console app:users:create --email=user@example.com --password=secret
```

**Important note:** users created with the command line have "super-admin" permissions and can do anything in Bileto.

Then, try to login via the interface, it should work.
You can start using Bileto now.

### Next steps

You may want to complete your setup with additional steps:

- setup a reverse proxy;
- create your first organization and adapt roles in Bileto;
- setup an LDAP server (see [the LDAP documentation in the “standard” documentation](/docs/administrators/deploy.md)).

## Updating Bileto

**Please always start by checking the migration notes in [the changelog](/CHANGELOG.md) before updating Bileto.**

Then, change the Docker image tag to the wanted version.

Pull the changes and restart the containers:

```console
$ docker compose pull
$ docker compose restart
```
