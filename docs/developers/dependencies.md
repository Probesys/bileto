# Managing the dependencies

The dependencies of Bileto are managed by:

- [Composer](https://getcomposer.org/) for the backend (see [`composer.json`](/composer.json));
- [npm](https://www.npmjs.com/) for the frontend (see [`package.json`](/package.json)).

The general philosophy about the dependencies is to limit them as much as possible.
We only add dependencies to relieve a pain.
Unfortunately, Symfony is split in a lot of different packages.
Thus, the file `package.json` is a bit heavy (but it is still manageable).

## Reminder about versions

Versions are usually using the [semver](https://semver.org) standard.
They are formatted as `major.minor.patch` where:

- `major` is a number incremented when incompatible API changes are made;
- `minor` is a number incremented when a new backwards compatible functionnality is added;
- `patch` is a number incremented when backwards bug fixes are made.

Be careful with versions `0.x.y`, the minor number is often considered as major.

## General advices

- you should check the changelog of the dependencies that you are updating;
- it should be fine to update the patch and minor versions in a batch;
- when upgrading to major versions, **always upgrade one dependency at a time;**
- always restart the Docker containers after an update and verify the application is not broken.

## Upgrade the Composer dependencies

Check the outdated dependencies with:

```console
$ ./docker/bin/composer outdated
```

Update with:

```console
$ ./docker/bin/composer update
```

For major versions upgrade, please update the requirements in the file `composer.json` and run the previous command.

After that, [run the linters and the tests](/docs/developers/tests.md) to check everything is fine.

## Upgrade Symfony

Symfony documents how to upgrade:

- [a patch version](https://symfony.com/doc/current/setup/upgrade_patch.html)
- [a minor version](https://symfony.com/doc/current/setup/upgrade_minor.html)
- [a major version](https://symfony.com/doc/current/setup/upgrade_major.html)

Patch and minor versions should be upgraded as soon as possible.
Major version should be upgraded accordingly to the minimal PHP version requirement of Bileto.

It is recommended to upgrade Doctrine and Twig with Symfony.

## Upgrade the NPM dependencies

Check the outdated dependencies with:

```console
$ ./docker/bin/npm outdated
```

Update with:

```console
$ ./docker/bin/npm update
```

For major versions upgrade, please update the requirements in the file `package.json` and run the previous command.

Also verify that building the assets still works:

```console
$ ./docker/bin/npm run build
```

Check the built assets work correctly by changing the path of the Twig `ViteTagsExtension` extension in the dev environment in the file [`config/services.yaml`](/config/services.yaml) (i.e. use `manifest.json` instead of `manifest.dev.json`).

It is also possible to perform a security audit of the dependencies with:

```console
$ ./docker/bin/npm audit
```

## Follow the Web feeds

It is recommended to follow the Web feeds of the main dependencies in an aggregator to be notified about new releases.

- [Stimulus feed](https://github.com/hotwired/stimulus/releases.atom) ([releases](https://github.com/hotwired/stimulus/releases))
- [Symfony feed](https://github.com/symfony/symfony/releases.atom) ([releases](https://github.com/symfony/symfony/releases))
- [Turbo feed](https://github.com/hotwired/turbo/releases.atom) ([releases](https://github.com/hotwired/turbo/releases))
