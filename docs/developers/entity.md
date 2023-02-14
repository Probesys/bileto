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

## Meta fields

All the entities must include a set of meta fields:

- `uid`: the id used in the URLs and forms (more difficult to guess than an incremental id)
- `createdAt`: the creation date of the entity
- `createdBy`: the user who created the entity

To handle that, please add the [`EntitySetMetaListener`](/src/EntityListener/EntitySetMetaListener.php) entity listener to your entity.
You must implement the [`MetaEntityInterface`](/src/Entity/MetaEntityInterface.php) interface too.
It requires to implements the setters and getters for the mentionned fields.

```php
namespace App\Entity;

use App\EntityListener\EntitySetMetaListener;
use Doctrine\ORM\Mapping as ORM;

#[ORM\EntityListeners([EntitySetMetaListener::class])]
class Foo implements MetaEntityInterface
{
    // ...
}
```

For this to work, the corresponding repository must implements the [`UidGeneratorInterface`](/src/Repository/UidGeneratorInterface.php).
This can be easily done by using the trait [`UidGeneratorTrait`](/src/Repository/UidGeneratorTrait.php).

```php
namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FooRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;

    // ...
}
```

## Migrations

Migrations can be generated with:

```console
$ make migration
```

And applied with:

```console
$ make db-migrate
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
$dbPlatform = $this->connection->getDatabasePlatform()->getName();
if ($dbPlatform === 'postgresql') {
    // here goes the SQL queries for the PostgreSQL database
} elseif ($dbPlatform === 'mysql') {
    // here goes the SQL queries for the MariaDB database
}
```

Then, generate the migration for the other database.
You can do that by changing the `DATABASE_URL` environment variable of the [`.env`](/.env) file (see the commented variable).
You must also reverse the commented database service in the [`docker-compose.yml`](/docker/docker-compose.yml) file and restart the Docker containers.
Don’t forget to re-setup the database:

```console
$ make db-reset FORCE=true
```

Then, re-run the `make migration` command, and move the generated code in the previous file.

You can now delete the last generated migration file, and reverse your changes:

```console
$ rm migrations/VersionXXXX.php
$ git checkout -- .env docker/docker-compose.yml
```

Restart the docker containers, and re-setup the database as previously.

**Note:** it is indeed quite inconvenient.
You’re very welcome to suggest a better system to handle migrations for several databases!
