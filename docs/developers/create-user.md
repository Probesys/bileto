# Creating a User

Creating a User involves several things that you should not forget such as encrypting the password and granting authorization based on different conditions.
For this reason, it's very discouraged to simply call the `UserRepository::save()` method to create a User.
Instead, you must use the [`UserCreator` service](/src/Service/UserCreator.php):

```php
use App\Service\UserCreator;
use App\Service\UserCreatorException;

public function createUser(UserCreator $userCreator)
{
    $email = 'alix@example.com';
    $name = 'Alix Hambourg';
    $password = 'secret';

    try {
        $user = $userCreator->create(
            email: $email,
            name: $name,
            password: $password,
        );
    } catch (UserCreatorException $e) {
        $errors = $e->getErrors();
        // Do something with the errors, such as displaying them in a form.
    }
}
```

If you already have a User entity, call `$userCreator->createUser($user)` instead.

The service automatically flushes the changes so Doctine writes the user to the database immediately.
If you want to delay the flush, you can pass `flush: false` to the methods.
