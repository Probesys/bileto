# Encrypting data

If you have to store sensitive data, you'll probably have to encrypt it first.
Bileto provides a convenient [`Encryptor`](/src/Security/Encryptor.php) class to encrypt and decrypt data.

To encrypt data:

```php
use App\Security\Encryptor;

public function storePassword(Encryptor $encryptor)
{
    $password = 'a super secret';

    $encryptedPassword = $encryptor->encrypt($password);

    // Save the encryptedPassword somewhere (e.g. in database).
}
```

To decrypt data:

```php
use App\Security\Encryptor;

public function loadPassword(Encryptor $encryptor)
{
    $encryptedPassword = /* load data */;

    $password = $encryptor->decrypt($encryptedPassword);

    // Use the password in your process
}
```

**Important note:** the Encryptor class requires a secret key.
It is automatically generated the first time you call the Encryptor and is stored in the file `var/data/encryptor.key`.
**You must take care not to delete or lose this file, otherwise you won't be able to decrypt the encrypted data anymore!**
The key is stored separately, so if the database is accessed by an attacker, they won't be able to decrypt the stored encrypted data.
