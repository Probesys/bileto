# Updating the development environment

Pull the changes with Git:

```console
$ git pull
```

If dependencies have been added or updated, install them:

```console
$ make install
```

While Bileto is not ready for the production yet, it is recommended to reset the database when a change happens:

```console
$ make db-reset FORCE=true
```

Later, you’ll just have to execute the migrations:

```console
$ make db-migrate
```

Sometimes, you may also have to rebuild the Docker image:

```console
$ make docker-build
```
