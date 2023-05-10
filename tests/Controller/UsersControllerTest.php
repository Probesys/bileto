<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UsersControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsUsersSortedByNameAndEmail(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne([
            'name' => 'Charlie Gature',
        ]);
        UserFactory::createOne([
            'name' => 'Benedict Aphone',
        ]);
        UserFactory::createOne([
            'name' => '',
            'email' => 'alix.pataques@example.com',
        ]);
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);

        $client->request('GET', '/users');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Users');
        $this->assertSelectorTextContains('[data-test="user-item"]:nth-child(1)', 'alix.pataques@example.com');
        $this->assertSelectorTextContains('[data-test="user-item"]:nth-child(2)', 'Benedict Aphone');
        $this->assertSelectorTextContains('[data-test="user-item"]:nth-child(3)', 'Charlie Gature');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/users');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);

        $client->request('GET', '/users/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New user');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/users/new');
    }

    public function testPostCreateCreatesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);
        $email = 'alix@example.com';
        $name = 'Alix Pataquès';
        $password = 'secret';

        $this->assertSame(1, UserFactory::count());

        $client->request('GET', '/users/new');
        $crawler = $client->submitForm('form-create-user-submit', [
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ]);

        $this->assertSame(2, UserFactory::count());
        $this->assertResponseRedirects('/users', 302);
        $newUser = UserFactory::last();
        $this->assertSame($email, $newUser->getEmail());
        $this->assertSame($name, $newUser->getName());
        $this->assertSame($user->getLocale(), $newUser->getLocale());
        $this->assertSame(20, strlen($newUser->getUid()));
        $this->assertTrue($passwordHasher->isPasswordValid($newUser->object(), $password));
    }

    public function testPostCreateFailsIfEmailIsAlreadyUsed(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = UserFactory::createOne([
            'email' => $email,
        ]);
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);
        $name = 'Alix Pataquès';

        $client->request('POST', '/users/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user'),
            'email' => $email,
            'name' => $name,
        ]);

        $this->assertSelectorTextContains(
            '#email-error',
            'Enter a different email address, this one is already in use',
        );
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostCreateFailsIfEmailIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);
        $email = '';
        $name = 'Alix Pataquès';

        $client->request('POST', '/users/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user'),
            'email' => $email,
            'name' => $name,
        ]);

        $this->assertSelectorTextContains('#email-error', 'Enter an email address');
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostCreateFailsIfEmailIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);
        $email = 'not an email';
        $name = 'Alix Pataquès';

        $client->request('POST', '/users/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user'),
            'email' => $email,
            'name' => $name,
        ]);

        $this->assertSelectorTextContains('#email-error', 'Enter a valid email address');
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);
        $email = 'alix@example.com';
        $name = 'Alix Pataquès';

        $client->request('POST', '/users/new', [
            '_csrf_token' => 'not a token',
            'email' => $email,
            'name' => $name,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $email = 'alix@example.com';
        $name = 'Alix Pataquès';

        $client->catchExceptions(false);
        $client->request('POST', '/users/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user'),
            'email' => $email,
            'name' => $name,
        ]);
    }
}
