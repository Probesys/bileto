# Working with the search engine

The search engine is made of several parts:

- the Query Parser;
- the Ticket Query Builder;
- and the Ticket Searcher.

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

The [Ticket Query Builder](/src/SearchEngine/QueryBuilder/TicketQueryBuilder.php) transforms a Query into a Doctrine DQL `WHERE` statement.
It is implemented as a service as it may need to access the database, or the current logged user.

To call the Query Builder, you have to inject it in some controller or service, and call its `build()` method:

```php
use App\SearchEngine;

$query = SearchEngine\Query::fromString('status:new');
list($whereStatement, $parameters) = $ticketQueryBuilder->build($query);
```

**It returns two elements: the DQL statement as a string, and an array of parameters indexed by the parameters names.**
They are directly usable by a Doctrine Query Builder:

```php
$qb = $this->createQueryBuilder('t');
$qb->andWhere($whereStatement);
foreach ($parameters as $key => $value) {
    $qb->setParameter($key, $value);
}
```

Internally, its functioning is quite simple.
It iterates over the conditions of the given query.
For each condition, it returns the DQL expression corresponding to the condition.
Each DQL expression is appended to the final string with an `AND` or `OR` keyword, depending on the condition operator.

Sometimes, it needs to preprocess a string value.
It is the case to transform the `@me` keyword by the id of the logged user.
It also can search for elements in the database (such as organizations or users) to replace the value by the corresponding id.

When an expression requiring a parameter is generated, the Query Builder register the parameter in a global list.
The name of the parameter is automatically generated.
For instance: `q0p0` where `q0` represents the first query and `p0` represents the first parameter.

It is possible to use multiple queries for a single Doctrine Query Builder.
In this case, pass the `$sequence` parameter to the `build()` method:

```php
$query1 = SearchEngine\Query::fromString('org:Probesys AND involves:@me');
list($whereStatement1, $parameters1) = $ticketQueryBuilder->build($query1, 1);

$query2 = SearchEngine\Query::fromString('status:new');
list($whereStatement2, $parameters2) = $ticketQueryBuilder->build($query2, 2);
```

In this example, the parameters names will start respectively by `q1` and `q2`.

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

**It takes a Query as an entry, and it returns either a list of tickets, or the number of tickets depending on the called method.**

It also makes sure that the current user only accesses the tickets that he has the permissions for.
For that, it limits the tickets with an internal query `involves:@me`.
You also can limit the result to a set of organizations:

```php
$organizations = /* Load some organizations */;
$ticketSearcher->setOrganizations($organizations);
```

In this case, the Searcher will check the permissions of the user for each organization and generates the correct (internal) query.
