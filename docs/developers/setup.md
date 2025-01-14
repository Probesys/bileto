# Setup the development environment

## Setup Docker

The development environment is managed with Docker by default.

First, make sure to install [Docker Engine](https://docs.docker.com/engine/install/).
The `docker` command must be executable by your normal user.

## Install Bileto

Clone the repository:

```console
$ git clone https://github.com/Probesys/bileto.git
```

Install the dependencies:

```console
$ make install
```

Start the development server:

```console
$ make docker-start
```

> [!TIP]
> You can change the port of the application by passing the `PORT` parameter:
>
> ```console
> $ make docker-start PORT=9000
> ```

Setup the database:

```console
$ make db-setup
```

Open [localhost:8000](http://localhost:8000) and login with one of the following credentials:

- `alix@example.com` / `secret` (super-admin and technician for all the organizations)
- `benedict@example.com` / `secret` (salesman for all the organizations)
- `charlie` / `secret` (LDAP user, in the “Friendly Coop” organization)
- `dominique` / `secret` (LDAP user, in the “Probesys” organization)

A note about the `make` commands: they might feel magic, but they are not!
They are just shortcuts for common commands.
If you want to know what they do, you can open the [Makefile](/Makefile) and locates the command that you are interested in.
They are hopefully easily readable by newcomers.

## Use MariaDB

By default, the development environment starts with a PostgreSQL database.
To use MariaDB, you must set the `DATABASE_URL` value in the `.env.dev.local` file:

```dotenv
DATABASE_URL="mysql://root:mariadb@mariadb:3306/bileto?serverVersion=10.4.29-MariaDB"
```

Then, restart the `docker-start` command with the `DATABASE` variable set to `mariadb`:

```console
$ make docker-start DATABASE=mariadb
```

You may have to setup or migrate the database:

```console
$ make db-setup
$ # or
$ make db-migrate
```

## Working in the Docker containers

There are few scripts to allow to execute commands in the Docker containers easily:

```console
$ ./docker/bin/php
$ ./docker/bin/composer
$ ./docker/bin/console
$ ./docker/bin/npm
$ ./docker/bin/psql
$ ./docker/bin/mariadb
```

## Reset the database

When developing, you may need to reset the database pretty often.
You can do it with the following command:

```console
$ make db-reset FORCE=true
```

You need to pass the `FORCE` argument, or the command will not be executed.

Resetting the database will also load the seeds.
You can prevent this by passing the `NO_SEED` argument:

```console
$ make db-reset FORCE=true NO_SEED=true
```
