# Logging information

Bileto provides a small service that helps to log messages in a structured way.
It is a thin wrapper around [`Psr\Log\LoggerInterface`](https://www.php-fig.org/psr/psr-3/) but it only provides a subset of its method (as we don't really need all of them for now).

Inject the service:

```php

use App\Service;

public function __construct(
    private readonly Service\Logger $logger,
) {
}
```

Then use it:

```php
// Call the logger as you would do anywhere else. In background, it gets the
// method which called it to add it automatically to the context.
$this->logger->critical('This is critical.');

// If you need to, you can force the caller value.
$this->logger->error('This is an error.', caller: 'MyClass::myMethod');

// You can also pass entities as parameter to give more context.
$this->logger->warning('This is a warning.', entities: [$ticket, $message]);

// Finally, pass any value that you want to complete the context.
$this->logger->notice('This is a notice.', context: [
    'myVariableValue' => $myVariable,
]);
```
