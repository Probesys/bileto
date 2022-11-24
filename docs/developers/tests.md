# Executing tests and linters

You can execute the tests with:

```console
$ make test
```

Execute the tests of a specific file with the `FILE=` parameter:

```console
$ make test FILE=tests/Controller/OrganizationsControllerTest.php
```

Filter tests with the `FILTER=` parameter (it takes a function name, or a part of it):

```
$ make test FILE=tests/Controller/OrganizationsControllerTest.php FILTER=testGetIndex
```

Execute the linters with:

```console
$ make lint
$ # or to fix errors
$ make lint-fix
```

The linters and the tests are executed on the CI, so you'll have to make sure they pass before we merge your pull request.
