# Declaring a new Entity

Entities are declared under the [`src/Entity` directory](/src/Entity).

You can declare a new Entity with the default Symfony console command:

```console
$ ./docker/bin/console make:entity
```

Then, don’t forget to create a new migration with:

```console
$ make migration
```

You should adapt the generated migrations, at least to handle both PostgreSQL and MariaDB databases (more on that below).

Documentation: [symfony.com](https://symfony.com/doc/current/doctrine.html).

## Monitorable entities

Almost all the entities must be “monitorable”.
The monitoring consists in two things:

- tracking the entities, i.e. their fields `createdAt`, `createdBy`, `updatedAt` and `updatedBy` are set automatically on insertions and updates
- recording the events of the entities, i.e. [`EntityEvents`](/src/Entity/EntityEvent.php) are created on insertions, updates and deletions

To handle that, you must implement [the `MonitorableEntityInterface` interface](/src/ActivityMonitor/MonitorableEntityInterface.php).
This can be done easily by using [the `MonitorableEntityTrait` trait](/src/ActivityMonitor/MonitorableEntityTrait.php).

```php
namespace App\Entity;

use App\ActivityMonitor;

class Foo implements ActivityMonitor\MonitorableEntityInterface
{
    use ActivityMonitor\MonitorableEntityTrait;

    // ...
}
```

If you need an entity to be only trackable or only recordable, you can implement one of the [`TrackableEntityInterface`](/src/ActivityMonitor/TrackableEntityInterface.php) or [`RecordableEntityInterface`](/src/ActivityMonitor/RecordableEntityInterface.php) interfaces with their corresponding traits.

The `createdBy` and `updatedBy` fields of the trackable entities are set with the value of the currently connected user by default.
However, sometimes you need to set these fields while there is no connected user (e.g. in a CLI context).
You can specify an active user by using the `ActiveUser::change()` method:

```php
class SomeService
{
    public function __construct(
        private ActiveUser $activeUser,
    ) {
    }

    public function someAction()
    {
        $user = /* Load some user from the database */;

        $this->activeUser->change($user);

        // Do some work and save entities
        // ...

        // Remember to reset the user at the end to avoid side-effects.
        $this->activeUser->change(null);
    }
}
```

To understand how the monitorable behaviours work, take a look at the implementations of the [`TrackableEntitiesSubscriber`](/src/ActivityMonitor/TrackableEntitiesSubscriber.php) and [`RecordableEntitiesSubscriber`](/src/ActivityMonitor/RecordableEntitiesSubscriber.php) subscribers.

## The UID field

All the entities have both id and uid fields.

The id is a standard incremented integer.
It can be used as an easy-to-remember id in some parts of the application (e.g. to find a ticket by its id).

However, we don't use these ids in URLs in order to avoid guessable URLs.
This is why we introduced uids.
A uid is a random string of 20 characters generated during database insertions.

To have robust uids, the entity must implement [the `UidEntityInterface` interface](/src/Uid/UidEntityInterface.php) with [the `UidEntityTrait` trait](/src/Uid/UidEntityTrait.php).

```php
namespace App\Entity;

use App\Uid\UidEntityInterface;
use App\Uid\UidEntityTrait;

class Foo implements UidEntityInterface
{
    use UidEntityTrait;

    // ...
}
```

For this to work, the corresponding repository must also implement [the `UidGeneratorInterface`](/src/Uid/UidGeneratorInterface.php).
This can be easily done by using [the `UidGeneratorTrait` trait](/src/Uid/UidGeneratorTrait.php).

```php
namespace App\Repository;

use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FooRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;

    // ...
}
```

Take a look to [the `UidEntitiesSubscriber` subscriber](/src/Uid/UidEntitiesSubscriber.php) to understand how it works.

## Migrations

Migrations can be generated with:

```console
$ make migration
```

And applied with:

```console
$ make db-migrate
```

If you need to rollback the last change (to fix the migration for instance), you can run:

```console
$ make db-rollback
```

### Rename the file

You should rename the generated file and class by appending a more comprehensive name.
For instance, rename `Version20230214161800` by `Version20230214161800CreateFoo` to indicate that the migration create the `foo` table.

This helps to find quickly a migration by simply browsing the files.

### Clear the file

Remove all the auto-generated comments, and add a comprehensive description in the `getDescription()` method.

### Add support for all databases types

Migrations must handle both PostgreSQL and MariaDB databases.
To do that, start by adding the following code to both `up()` and `down()` methods:

```php
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

// ...

$dbPlatform = $this->connection->getDatabasePlatform();
if ($dbPlatform instanceof PostgreSQLPlatform) {
    // here goes the SQL queries for the PostgreSQL database
} elseif ($dbPlatform instanceof MariaDBPlatform) {
    // here goes the SQL queries for the MariaDB database
}
```

Then, generate the migration for MariaDB.
You can do that by changing the `DATABASE_URL` environment variable of the [`.env`](/.env) file (see the commented variable).
You must also restart the Docker containers by enabling MariaDB:

```console
$ make docker-start DATABASE=mariadb
```

Then, re-run the `make migration` command, and move the generated code in the previous file.

You can now delete the last generated migration file, and reverse your changes:

```console
$ rm migrations/VersionXXXX.php
$ git restore .env
```

Restart the docker containers to use PostgreSQL:

```console
$ make docker-start
```

**Note:** it is indeed quite inconvenient.
You’re very welcome to suggest a better system to handle migrations for several databases!
See ticket [#230](https://github.com/Probesys/bileto/issues/230).
