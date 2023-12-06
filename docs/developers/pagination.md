# How to paginate the entities

Instead of loading and displaying all the entities of a particular type from the database all at once, you can choose to paginate them.
You can do it easily with the [`\App\Utils\Pagination`](/src/Utils/Pagination.php) class.

To paginate a Doctrine query in a repository for instance:

```php

use App\Utils\Pagination;

// ...

public function findAllPaginated(int $page, int $maxResults): Pagination
{
    $entityManager = $this->getEntityManager();

    $query = $entityManager->createQuery(<<<DQL
        SELECT t
        FROM App\Entity\Ticket t
    DQL);

    return Pagination::paginate($query, [
        'page' => $page,
        'maxResults' => $maxResults,
    ]);
}
```

The `paginate()` method takes a `\Doctrine\ORM\Query` and some options to paginate the results of the query.
The options are a simple array with the keys `page` (the page number to retrieve) and a `maxResults` (the number of results per page).

Then, pass the `Pagination` object to the template.

You can iterate over the objects:

```twig
{% for ticket in ticketsPagination.items %}
    <h2>{{ ticket.title }}</h2>
{% enfor %}
```

To generate the pagination:

```twig
{{ include('_pagination.html.twig', { pagination: ticketsPagination }) }}
```

**Take a look at [the `Pagination` class](/src/Utils/Pagination.php) for more useful methods.**
