# Bileto

Welcome on the Bileto repository. Bileto is our future software of service desk and IT asset management.

It is written with [Symfony](https://symfony.com/) 6.1 and works with [PHP](https://www.php.net/) 8.1.

At the moment, the only database officially supported is PostgreSQL 14.

Bileto is licensed under [GNU Affero General Public License v3.0 or later](LICENSE.txt).

## Contributing

Ideas, bug reports and contributions are welcome. Please follow [the contributing guide](CONTRIBUTING.md).

## Developer guide

### Setup the development environment (Docker)

The development environment is managed with Docker by default.

First, make sure to install [Docker Engine](https://docs.docker.com/engine/install/) and [Docker Compose](https://docs.docker.com/compose/install/). Both `docker` and `docker-compose` must be executable by your normal user.

Clone the repository:

```console
$ git clone https://github.com/Probesys/bileto.git
```

Install the dependencies:

```console
$ make install
```

Setup the database:

```console
$ make db-setup
```

Start the development server:

```console
$ make docker-start
```

And open [localhost:8000](http://localhost:8000).

### Working in the Docker containers

There are few scripts to allow to execute commands in the Docker container easily:

```console
$ ./docker/bin/php
$ ./docker/bin/composer
$ ./docker/bin/console
$ ./docker/bin/npm
$ ./docker/bin/psql
```

### Executing tests and linters

You can execute the tests with:

```console
$ make test
```

And the linters with:

```console
$ make lint
$ # or to fix errors
$ make lint-fix
```

The linters and the tests are executed on the CI, so you'll have to make sure they pass.

### Updating the application

Pull the changes with Git:

```console
$ git pull
```

Execute the migrations:

```console
$ make db-migrate
```

Sometimes, you may also have to rebuild the Docker image:

```console
$ make docker-build
```
