<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProfileControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Profile');
    }

    public function testGetEditRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/profile');

        $this->assertResponseRedirects('/login', 302);
    }

    public function testPostUpdateSavesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $initialName = Factory::faker()->unique()->userName();
        $newName = Factory::faker()->unique()->userName();
        $initialEmail = Factory::faker()->unique()->email();
        $newEmail = Factory::faker()->unique()->email();
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->object());

        $client->request('GET', '/profile');
        $crawler = $client->submitForm('form-update-profile-submit', [
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertResponseRedirects('/profile', 302);
        $user->refresh();
        $this->assertSame($newName, $user->getName());
        $this->assertSame($newEmail, $user->getEmail());
    }

    public function testPostUpdateChangesThePassword(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $initialPassword = Factory::faker()->unique()->password();
        $newPassword = Factory::faker()->unique()->password();
        $user = UserFactory::createOne([
            'password' => $initialPassword,
        ]);
        $client->loginUser($user->object());

        $client->request('POST', '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'currentPassword' => $initialPassword,
            'newPassword' => $newPassword,
        ]);

        $this->assertResponseRedirects('/profile', 302);
        $user->refresh();
        $this->assertFalse($passwordHasher->isPasswordValid($user->object(), $initialPassword));
        $this->assertTrue($passwordHasher->isPasswordValid($user->object(), $newPassword));
    }

    public function testPostUpdateFailsIfNameIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Factory::faker()->unique()->userName();
        $newName = str_repeat('a', 101);
        $initialEmail = Factory::faker()->unique()->email();
        $newEmail = Factory::faker()->unique()->email();
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->object());

        $client->request('POST', '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertSelectorTextContains('#name-error', 'Enter a name of less than 100 characters');
        $user->refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostUpdateFailsIfEmailIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Factory::faker()->unique()->userName();
        $newName = Factory::faker()->unique()->userName();
        $initialEmail = Factory::faker()->unique()->email();
        $newEmail = 'not an email';
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->object());

        $client->request('POST', '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertSelectorTextContains('#email-error', 'Enter a valid email address');
        $user->refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }

    public function testPostUpdateFailsIfCurrentPasswordIsInvalid(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $initialPassword = Factory::faker()->unique()->password();
        $newPassword = Factory::faker()->unique()->password();
        $user = UserFactory::createOne([
            'password' => $initialPassword,
        ]);
        $client->loginUser($user->object());

        $client->request('POST', '/profile', [
            '_csrf_token' => $this->generateCsrfToken($client, 'update profile'),
            'currentPassword' => 'not the password',
            'newPassword' => $newPassword,
        ]);

        $this->assertSelectorTextContains(
            '#current-password-error',
            'The password does not match, please try with a different one',
        );
        $user->refresh();
        $this->assertTrue($passwordHasher->isPasswordValid($user->object(), $initialPassword));
        $this->assertFalse($passwordHasher->isPasswordValid($user->object(), $newPassword));
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $initialName = Factory::faker()->unique()->userName();
        $newName = Factory::faker()->unique()->userName();
        $initialEmail = Factory::faker()->unique()->email();
        $newEmail = Factory::faker()->unique()->email();
        $user = UserFactory::createOne([
            'name' => $initialName,
            'email' => $initialEmail,
        ]);
        $client->loginUser($user->object());

        $client->request('POST', '/profile', [
            '_csrf_token' => 'not the token',
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $user->refresh();
        $this->assertSame($initialName, $user->getName());
        $this->assertSame($initialEmail, $user->getEmail());
    }
}
