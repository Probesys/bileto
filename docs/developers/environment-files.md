# Managing environment files

Bileto is configured through environment files (e.g. `.env`).
An environment file declares a bunch of environment variables which are then used in the application.

Learn more about environment files in [the Symfony documentation.](https://symfony.com/doc/current/configuration.html#configuration-based-on-environment-variables)

## Environment files

Bileto provides several environment files:

- [`.env`](/.env): this is the file where the default values are declared. These values are used in all the environments.
- [`.env.dev`](/.env.dev): this file overrides the variables for the development environment.
- [`.env.test`](/.env.test): this file overrides the variables for the test environment.
- [`env.sample`](/env.sample): this file is not directly used by Bileto, but it must be copied as `.env.local`. It is then adapted to the need of the administrator or developer.

## How to add an environment variable

When you need to add a new configuration option, you'll have to declare a new environment variable.

First, add the variable with an understandable value to the `env.sample` file.
The variable must be documented with a comment.
It must be placed in the correct section (e.g. "Configuraton of the application").
If the value is a string, please always put it in double quotes as a good practice.

Always comment the variable itself in this file, unless it is absolutely required that the administrator set it.
In this case, you must also add a note to the "Breaking changes" section of [the changelog](/CHANGELOG.md).

Then, add a default value to the `.env` file (unless it can be null).
We should consider that administrators will forget to set this variable in production, so it's important that this value is suitable for production.

If necessary (and only if necessary), declare the variable in the `.env.dev` and `.env.test` files.

You don't need to duplicate the comment in the `.env*` files as it would be harder to maintain if it should be changed in the `env.sample` file.
However, please always include the environment sections in order to ease the read of these files.

## How to use an environment variable

Once declared, you can use the variable either in the Symfony configuration (ie. under the [`config/` folder](/config), or directly in a service:

```php
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MyService
{
    public function __construct(
        #[Autowire(env: 'APP_MY_VARIABLE')]
        private string $myVariable,
    ) {
    }
}
```

You can use the [Symfony environment variable processors](https://symfony.com/doc/current/configuration/env_var_processors.html) to cast the value of the variable from a string to another type.
