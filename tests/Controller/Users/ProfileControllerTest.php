<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Users;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProfileControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_GET, '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Profile');
    }

    public function testGetEditRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/profile');

        $this->assertResponseRedirects('/login', 302);
    }

    public function testPostEditSavesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = Factory\UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            'profile' => [
                '_token' => $this->generateCsrfToken($client, 'profile'),
                'name' => $newName,
                'email' => $newEmail,
            ],
        ]);

        $this->assertResponseRedirects('/profile', 302);
        $user->_refresh();
        $this->assertSame($newName, $user->getName());
        $this->assertSame($newEmail, $user->getEmail());
    }

    public function testPostEditChangesThePassword(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $initialPassword = Foundry\faker()->unique()->password();
        $newPassword = Foundry\faker()->unique()->password();
        $user = Factory\UserFactory::createOne([
            'password' => $initialPassword,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            'profile' => [
                '_token' => $this->generateCsrfToken($client, 'profile'),
                'name' => 'Alix',
                'email' => 'alix@example.coop',
                'currentPassword' => $initialPassword,
                'plainPassword' => $newPassword,
            ],
        ]);

        $this->assertResponseRedirects('/profile', 302);
        $user->_refresh();
        $this->assertFalse($passwordHasher->isPasswordValid($user->_real(), $initialPassword));
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $newPassword));
    }

    public function testPostEditFailsIfNameIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = str_repeat('a', 101);
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = Factory\UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            'profile' => [
                '_token' => $this->generateCsrfToken($client, 'profile'),
                'name' => $newName,
                'email' => $newEmail,
            ],
        ]);

        $this->assertSelectorTextContains('#profile_name-error', 'Enter a name of less than 100 characters');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostEditFailsIfEmailIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = 'not an email';
        $user = Factory\UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            'profile' => [
                '_token' => $this->generateCsrfToken($client, 'profile'),
                'name' => $newName,
                'email' => $newEmail,
            ],
        ]);

        $this->assertSelectorTextContains('#profile_email-error', 'Enter a valid email address');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostEditFailsIfCurrentPasswordIsInvalid(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $initialPassword = Foundry\faker()->unique()->password();
        $newPassword = Foundry\faker()->unique()->password();
        $user = Factory\UserFactory::createOne([
            'password' => $initialPassword,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            'profile' => [
                '_token' => $this->generateCsrfToken($client, 'profile'),
                'name' => 'Alix',
                'email' => 'alix@example.coop',
                'currentPassword' => 'not the password',
                'plainPassword' => $newPassword,
            ],
        ]);

        $this->assertSelectorTextContains(
            '#profile_currentPassword-error',
            'The password does not match, please try with a different one',
        );
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $initialPassword));
        $this->assertFalse($passwordHasher->isPasswordValid($user->_real(), $newPassword));
    }

    public function testPostEditFailsIfManagedByLdap(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = Factory\UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
            'ldapIdentifier' => 'charlie',
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            'profile' => [
                '_token' => $this->generateCsrfToken($client, 'profile'),
                'name' => $newName,
                'email' => $newEmail,
            ],
        ]);

        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = Factory\UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            'profile' => [
                '_token' => 'not the token',
                'name' => $newName,
                'email' => $newEmail,
            ],
        ]);

        $this->assertSelectorTextContains('#profile-error', 'The security token is invalid');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }
}
