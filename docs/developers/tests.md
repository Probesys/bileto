# Executing tests and linters

The linters and the tests are executed on the CI, so you'll have to make sure they pass before we merge your pull request.

## Executing the tests

You can execute the tests with:

```console
$ make test
```

Execute the tests of a specific file with the `FILE=` parameter:

```console
$ make test FILE=tests/Controller/OrganizationsControllerTest.php
```

Filter tests with the `FILTER=` parameter (it takes a function name, or a part of it):

```console
$ make test FILE=tests/Controller/OrganizationsControllerTest.php FILTER=testGetIndex
```

## Code coverage

The previous command generates code coverage under the folder `coverage/`.
To disable code coverage, run the command:

```console
$ make test COVERAGE=
```

## Running the linters

Execute the linters with:

```console
$ make lint
$ # or to fix errors
$ make lint-fix
```

You can run a specific linter with:

```console
$ make lint LINTER=phpstan
$ make lint LINTER=rector
$ make lint LINTER=phpcs
$ make lint LINTER=symfony
$ make lint LINTER=js
$ make lint LINTER=css
```
