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

Setup the database:

```console
$ make db-setup
```

Open [localhost:8000](http://localhost:8000) and login with one of the following credentials:

- `alix@example.com` / `secret` (super-admin and technician for all the organizations)
- `benedict@example.com` / `secret` (user in the “Web team” organization)
- `charlie@example.com` / `secret` (user in the “Friendly Coorp” organization)

A note about the `make` commands: they might feel magic, but they are not!
They are just shortcuts for common commands.
If you want to know what they do, you can open the [Makefile](/Makefile) and locates the command that you are interested in.
They are hopefully easily readable by newcomers.

## Use MariaDB

By default, `make docker-start` starts a PostgreSQL database.
If you want to use MariaDB, just pass the `DATABASE` variable to the command:

```console
$ make docker-start DATABASE=mariadb
```

You’ll also need to change the `DATABASE_URL` value in the [`.env` file](/.env) (just uncomment the second line).
If you want to make this change permanent, create a `.env.local` file and copy the line into it.

## Working in the Docker containers

There are few scripts to allow to execute commands in the Docker containers easily:

```console
$ ./docker/bin/php
$ ./docker/bin/composer
$ ./docker/bin/console
$ ./docker/bin/npm
$ ./docker/bin/psql
```
