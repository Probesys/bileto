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
- `benedict@example.com` / `secret` (salesman for all the organizations)
- `charlie@example.com` / `secret` (user in the “Friendly Coop” organization)

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

## Work with LDAP

The LDAP server is not started by default.
To start the LDAP server, pass the `LDAP` variable to the command:

```console
$ make docker-start LDAP=true
```

You’ll also have to create an `.env.local` file to enable LDAP support in Bileto:

```dotenv
LDAP_ENABLED=true
```

Everything else is already configured in the [`.env`](/.env) file.

You can login with two users with LDAP:

- `charlie` / `secret` (same as the previous `charlie@example.com` user, instead that you can't login with its email anymore)
- `dominique` / `secret` (this user is created at login, so they have no organization nor permissions)

You can still log in using the `alix@example.com` and `benedict@example.com` emails.

## Working in the Docker containers

There are few scripts to allow to execute commands in the Docker containers easily:

```console
$ ./docker/bin/php
$ ./docker/bin/composer
$ ./docker/bin/console
$ ./docker/bin/npm
$ ./docker/bin/psql
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
