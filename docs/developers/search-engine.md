# Search engine

The search engine is made of several parts:

- the Query Parser;
- the Ticket Query Builder;
- the Ticket Searcher;
- and the Ticket Filter.

**The code of the search engine is placed under [the `src/SearchEngine/` folder](/src/SearchEngine).**

## The Query Parser

The Query Parser is a generic parser.
**It takes a string as an entry, and it returns a [`Query`](/src/SearchEngine/Query.php).**
A Query is an abstraction of a textual query.
It can be transformed into a Doctring DQL `WHERE` statement thanks to a Query Builder (see below).

The Query can be initialized with the following code:

```php
use App\SearchEngine;

$query = SearchEngine\Query::fromString('status:new');
```

The parser is made of two different parts itself: the [`Tokenizer`](/src/SearchEngine/Query/Tokenizer.php) and the [`Parser`](/src/SearchEngine/Query/Parser.php).

The Tokenizer takes a string as an entry, and it returns a list of tokens.
A token is an abstraction of a small section of the textual query.
For instance, `status:` is represented by a `Qualifier` token, and `new` is represented by a `Text` token.
There are more tokens, such as `Comma`, `And`, or `Not`.

[See the full list of tokens.](/src/SearchEngine/Query/TokenType.php)

The tokens are then passed to the Parser.
The Parser is responsible of the logic of the query.
It makes sure that a condition is following an `OR` or an `AND` operator for instance.
The logic is defined by a [LL grammar](https://en.wikipedia.org/wiki/LL_grammar).
You can find the grammar at the top of [the `Parser` file](/src/SearchEngine/Query/Parser.php).
If the textual query matches the grammar, the Parser will return a Query.

## The Ticket Query Builder

The [Ticket Query Builder](/src/SearchEngine/QueryBuilder/TicketQueryBuilder.php) transforms a list of Queries into a Doctrine QueryBuilder.
It is implemented as a service as it may need to access the database, or the current logged user.

To call the Ticket Query Builder, you have to inject it in some controller or service, and call its `create()` method:

```php
use App\SearchEngine;

$query = SearchEngine\Query::fromString('status:new');
$queryBuilder = $ticketQueryBuilder->create([$query]);
```

The Doctrine Query Builder can be then used to fetch tickets from the database.

Internally, its functioning is quite simple.
It first create a Doctrine Query Builder for the ticket table.
If necessary, it joins additional tables based on the conditions of the queries.

Then, it iterates over the queries and their conditions.
For each condition, it returns the DQL expression corresponding to the condition.
Each DQL expression is appended to the final string with an `AND` or `OR` keyword, depending on the condition operator.

Sometimes, it needs to preprocess a string value.
It is the case to transform the `@me` keyword by the id of the logged user.
It also can search for elements in the database (such as organizations or users) to replace the value by the corresponding id.

When an expression requiring a parameter is generated, the Query Builder register the parameter in a global list.
The name of the parameter is automatically generated.
For instance: `q0p0` where `q0` represents the first query and `p0` represents the first parameter.

## The Ticket Searcher

The [Ticket Searcher](/src/SearchEngine/TicketSearcher.php) does the plumbing between the different parts of the search engine.

```php
$queryString = 'status:new';

try {
    $query = SearchEngine\Query::fromString($queryString);
    $tickets = $ticketSearcher->getTickets($query);
    $ticketsCount = $ticketSearcher->countTickets($query);
} catch (\Exception $e) {
    $error = $e->getMessage();
}
```

**It takes a Query as an entry, and it returns either a (paginated) list of tickets, or the number of tickets depending on the called method.**

It also makes sure that the current user only accesses the tickets that he has the permissions for.
For that, it limits the tickets with an internal query `involves:@me`.
You also can limit the result to a set of organizations:

```php
$organizations = /* Load some organizations */;
$ticketSearcher->setOrganizations($organizations);
```

In this case, the Searcher will check the permissions of the user for each organization and generates the correct (internal) query.

## The Ticket Filter

The [Ticket Filter](/src/SearchEngine/TicketFilter.php) is an additionnal layer to manage the “quick search” mode.

**It extracts a list of simple conditions from a Query.**
Once the Ticket Filter is initialized, you can programmatically change the filters.
Then, you can print the new textual query so it can be passed in an URL for instance.
This allows the filter system to manipulate a Query easily.

```php
use App\SearchEngine;

$query = SearchEngine\Query::fromString('status:new');
$ticketFilter = SearchEngine\TicketFilter::fromQuery($query);

var_dump($ticketFilter->getFilter('status')); // display ['new']

$ticketFilter->setFilter('status', ['new', 'in_progress']);
var_dump($ticketFilter->getFilter('status')); // display ['new', 'in_progress']

$ticketFilter->setText('email');

var_dump($ticketFilter->toTextualQuery()); // display 'email status:new,in_progress'
```

If the initial Query is too complex for the Ticket Filter (e.g. it contains a sub-query), the method `fromQuery()` returns null.

You can find more about it in the [`Tickets/FiltersController`](/src/Controller/Tickets/FiltersController.php).
