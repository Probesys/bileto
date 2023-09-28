# The Docker images

We provide Docker images ready for production.
These images are built and pushed to the GitHub Container Registry by using [a GitHub Action](/.github/workflows/docker-image.yml).
The images are built for tags (the image tag corresponds to the Git tag then), and for the `main` branch (the image tag is `edge` then).

The `edge` branch is used for our preproduction server, so we can test the new features before releasing a new version.

**[Learn how to use the production Docker image.](/docs/administrators/docker.md)**

## The production Dockerfile

The Dockerfile is located at [`docker/production/Dockerfile`](/docker/production/Dockerfile).

The first stage builds the assets of the application so the `edge` version comes with the correct assets.

Then, in a second stage, we install the application with its dependencies.
The assets are copied in this second stage from the first one to replace the default assets.
The image is configured with the following aspects:

- it uses Apache to serve the Web requests;
- it is configured with the timezone `Europe/Paris` by default (we can consider to allow to override it somehow);
- the containers run as the `www-data` user.

The image is configured with an entrypoint.
It allows to:

- migrate the database automatically when the container (re)starts;
- mark the container as healthy, so that other containers can rely on it more reliably.

## Build an image

The images can be built manually by using the `make docker-image` command.
For instance:

```console
$ make docker-image VERSION=edge
```

This command builds the image `ghcr.io/probesys/bileto:edge`.
