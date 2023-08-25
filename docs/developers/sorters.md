# Sorting the entities

As you display entities in the interface, you’ll probably want to sort them by some fields.
Most likely you will want to sort them by names or titles.

A naive solution is either to let the database sort them, or to do a simple string comparison.
Please don’t!
These two solutions ignore the user's current locale, which affects the expected sort order.

The PHP class [`Collator`](https://www.php.net/manual/class.collator.php) provides locale sensitive string comparison functionality.
In Bileto, this class is automatically configured with the current user locale thanks to our [`LocaleSorter`](/src/Service/Sorter/LocaleSorter.php) class.
It is extended by a number of subclasses:

- [`AuthorizationSorter`](/src/Service/Sorter/AuthorizationSorter.php)
- [`MailboxSorter`](/src/Service/Sorter/MailboxSorter.php)
- [`OrganizationSorter`](/src/Service/Sorter/OrganizationSorter.php)
- [`RoleSorter`](/src/Service/Sorter/RoleSorter.php)
- [`UserSorter`](/src/Service/Sorter/UserSorter.php)

Please always use these ones when you need to sort the corresponding entities.
For instance:

```php
use \App\Service\Sorter\UserSorter;

public function someController(UserSorter $userSorter)
{
    $users = /* load users */
    $userSorter->sort($users);

    // The rest of your controller.
}
```

The main exception is the sorting of the tickets.
For performance reasons, they are sorted directly by the database.
You’ll need to use the [`TicketSearcher`](/src/SearchEngine/TicketSearcher.php) class to get and sort tickets.
