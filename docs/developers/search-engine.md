# Search engine

The search engine is built upon a generic Query parser.
At the moment, the search engine is only able to search for tickets.

Two forms are provided to search tickets:

- The Advanced Search Form: it allows to manipulate the query textually, for the advanced users.
- The Quick Search Form: it provides a more intuitive experience to search tickets.

**Most of the code of the search engine is placed under [the `src/SearchEngine/` folder](/src/SearchEngine).**
The forms are placed under [`src/Form/Ticket`](/src/Form/Ticket).

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

## The Advanced Search Form

The [`AdvancedSearchForm`](/src/Form/Ticket/AdvancedSearchForm.php) transforms a textual query to a generic `Query` as explained above.

## The Quick Search Form

The [`QuickSearchForm`](/src/Form/Ticket/QuickSearchForm.php) handles data through a [`Ticket\QuickSearchFilter`](/src/SearchEngine/Ticket/QuickSearchFilter.php).

The `QuickSearchFilter` is initially built from the `Query` returned by the Advanced Search Form.
It is only able to handle a subset of the Query conditions.
If the query contains unsupported conditions, the Quick Search Form is initialized with no data.

Once the `QuickSearchFilter` is initialized, you can programmatically change the filters.

Once submitted, the `QuickSearchFilter` is transformed into a textual query in the [`Tickets\QuickSearchesController`](/src/Controller/Tickets/QuickSearchesController.php`).

This system allows the search system to manipulate a Query easily.

## The Ticket Searcher

Once submitted, a textual query is transformed to a generic `Query`, as seen above.
**This `Query` is then passed to the [`Ticket\Searcher`](/src/SearchEngine/Ticket/Searcher.php).**
This is the `Searcher` which is in charge of transforming the `Query` into a Doctrine query (through the `Ticket\QueryBuilder`, see below) and to paginate the results.

To call the `Searcher`, you have to inject it in some controller or service, and call its `getTickets()` or `countTickets()` methods:

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

It also makes sure that the current user only accesses the tickets that he has the permissions for.
For that, it limits the tickets with an internal query `involves:@me`.
You also can limit the result to a set of organizations:

```php
$organizations = /* Load some organizations */;
$ticketSearcher->setOrganizations($organizations);
```

In this case, the Searcher will check the permissions of the user for each organization and generates the correct (internal) query.

## The Ticket Query Builder

The [`Ticket\QueryBuilder`](/src/SearchEngine/Ticket/QueryBuilder.php) transforms a list of Queries into a Doctrine QueryBuilder.
It inherits from the generic [`QueryBuilder` class](/src/SearchEngine/QueryBuilder.php).

Internally, its functioning is quite simple.
It first create a Doctrine Query Builder for the ticket table.

Then, it iterates over the queries and their conditions.
For each condition, it returns the DQL expression corresponding to the condition.
Each DQL expression is appended to the final string with an `AND` or `OR` keyword, depending on the condition operator.

Sometimes, it needs to preprocess a string value.
It is the case to transform the `@me` keyword by the id of the logged user.
It also can search for elements in the database (such as organizations or users) to replace the value by the corresponding id.

When an expression requiring a parameter is generated, the Query Builder register the parameter in a global list.
The name of the parameter is automatically generated.
For instance: `q0p0` where `q0` represents the first query and `p0` represents the first parameter.
