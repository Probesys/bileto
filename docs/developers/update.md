# Updating the development environment

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
