# Updating the production environment

**Please always start by checking the migration notes in [the changelog](/CHANGELOG.md) before updating Bileto.**

Remember that commands prefixed by `www-data$` need to be run as the `www-data` user.
[Read more about file permissions.](/docs/administrators/deploy.md#about-file-permissions)

Pull the changes with Git:

```console
www-data$ git fetch
```

Switch to the latest version:

```console
www-data$ git switch $(git describe --tags $(git rev-list --tags --max-count=1))
```

Install the new/updated dependencies:

```console
www-data$ composer install --no-dev --optimize-autoloader
```

Execute the migrations:

```console
www-data$ php bin/console doctrine:migrations:migrate --no-interaction
```

Finally, restart the Messenger worker with:

```console
# systemctl restart bileto-worker
```
