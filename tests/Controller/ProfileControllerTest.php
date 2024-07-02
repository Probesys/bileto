<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\FactoriesHelper;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProfileControllerTest extends WebTestCase
{
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
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

    public function testPostUpdateSavesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_GET, '/profile');
        $crawler = $client->submitForm('form-update-profile-submit', [
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertResponseRedirects('/profile', 302);
        $user->_refresh();
        $this->assertSame($newName, $user->getName());
        $this->assertSame($newEmail, $user->getEmail());
    }

    public function testPostUpdateChangesThePassword(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $initialPassword = Foundry\faker()->unique()->password();
        $newPassword = Foundry\faker()->unique()->password();
        $user = UserFactory::createOne([
            'password' => $initialPassword,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'currentPassword' => $initialPassword,
            'newPassword' => $newPassword,
        ]);

        $this->assertResponseRedirects('/profile', 302);
        $user->_refresh();
        $this->assertFalse($passwordHasher->isPasswordValid($user->_real(), $initialPassword));
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $newPassword));
    }

    public function testPostUpdateFailsIfNameIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = str_repeat('a', 101);
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertSelectorTextContains('#name-error', 'Enter a name of less than 100 characters');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostUpdateFailsIfEmailIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = 'not an email';
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertSelectorTextContains('#email-error', 'Enter a valid email address');
        $this->clearEntityManager();
        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostUpdateFailsIfCurrentPasswordIsInvalid(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $initialPassword = Foundry\faker()->unique()->password();
        $newPassword = Foundry\faker()->unique()->password();
        $user = UserFactory::createOne([
            'password' => $initialPassword,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'currentPassword' => 'not the password',
            'newPassword' => $newPassword,
        ]);

        $this->assertSelectorTextContains(
            '#current-password-error',
            'The password does not match, please try with a different one',
        );
        $user->_refresh();
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $initialPassword));
        $this->assertFalse($passwordHasher->isPasswordValid($user->_real(), $newPassword));
    }

    public function testPostUpdateFailsIfManagedByLdap(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
            'ldapIdentifier' => 'charlie',
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertSelectorTextContains(
            '[data-test="alert-error"]',
            'You can’t update your profile because it’s managed by LDAP.',
        );
        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Foundry\faker()->unique()->userName();
        $newName = Foundry\faker()->unique()->userName();
        $initialEmail = Foundry\faker()->unique()->email();
        $newEmail = Foundry\faker()->unique()->email();
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_POST, '/profile', [
            '_csrf_token' => 'not the token',
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $user->_refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }
}
