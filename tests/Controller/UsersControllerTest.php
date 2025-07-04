<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\AuthorizationHelper;
use App\Tests\FactoriesHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UsersControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
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
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_GET, '/users');

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
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/users');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_GET, '/users/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New user');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/users/new');
    }

    public function testPostNewCreatesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $email = 'alix@example.com';
        $name = 'Alix Pataquès';
        $password = 'secret';
        $locale = 'fr_FR';

        $this->assertSame(1, UserFactory::count());

        $client->request(Request::METHOD_POST, '/users/new', [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $email,
                'name' => $name,
                'plainPassword' => $password,
                'locale' => $locale,
            ],
        ]);

        $this->assertSame(2, UserFactory::count());
        $newUser = UserFactory::last();
        $this->assertResponseRedirects("/users/{$newUser->getUid()}/authorizations/new", 302);
        $this->assertSame($email, $newUser->getEmail());
        $this->assertSame($name, $newUser->getName());
        $this->assertSame($locale, $newUser->getLocale());
        $this->assertSame(20, strlen($newUser->getUid()));
        $this->assertTrue($passwordHasher->isPasswordValid($newUser->_real(), $password));
        $this->assertNull($newUser->getOrganization());
    }

    public function testPostNewFailsIfEmailIsAlreadyUsed(): void
    {
        $client = static::createClient();
        $email = 'alix@example.com';
        $user = UserFactory::createOne([
            'email' => $email,
        ]);
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $name = 'Alix Pataquès';

        $client->request(Request::METHOD_POST, '/users/new', [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $email,
                'name' => $name,
                'locale' => 'en_GB',
            ],
        ]);

        $this->assertSelectorTextContains(
            '#user_email-error',
            'Enter a different email address, this one is already in use',
        );
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostNewFailsIfEmailIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $email = '';
        $name = 'Alix Pataquès';

        $client->request(Request::METHOD_POST, '/users/new', [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $email,
                'name' => $name,
                'locale' => 'en_GB',
            ],
        ]);

        $this->assertSelectorTextContains('#user_email-error', 'Enter an email address');
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostNewFailsIfEmailIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $email = 'not an email';
        $name = 'Alix Pataquès';

        $client->request(Request::METHOD_POST, '/users/new', [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $email,
                'name' => $name,
                'locale' => 'en_GB',
            ],
        ]);

        $this->assertSelectorTextContains('#user_email-error', 'Enter a valid email address');
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $email = 'alix@example.com';
        $name = 'Alix Pataquès';

        $client->request(Request::METHOD_POST, '/users/new', [
            'user' => [
                '_token' => 'not a token',
                'email' => $email,
                'name' => $name,
                'locale' => 'en_GB',
            ],
        ]);

        $this->assertSelectorTextContains('#user-error', 'The security token is invalid');
        $this->assertSame(1, UserFactory::count());
    }

    public function testGetShowRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $user->getDisplayName());
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}");
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit a user');
    }

    public function testGetEditFailsIfUserIsAnonymized(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne([
            'anonymizedAt' => Utils\Time::now(),
        ]);
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}/edit");
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}/edit");
    }

    public function testPostEditSavesTheUser(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $oldEmail = 'alix@example.com';
        $newEmail = 'benedict@example.com';
        $oldName = 'Alix Pataquès';
        $newName = 'Benedict Aphone';
        $oldPassword = 'secret';
        $newPassword = 'super secret';
        $oldLocale = 'en_GB';
        $newLocale = 'en_GB';
        $oldOrganization = OrganizationFactory::createOne();
        $newOrganization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne([
            'email' => $oldEmail,
            'name' => $oldName,
            'password' => $oldPassword,
            'organization' => $oldOrganization,
            'locale' => $oldLocale,
        ]);
        // This is required so the organizations are accessible to the user.
        $this->grantOrga($otherUser->_real(), ['orga:create:tickets'], type: 'user');

        $result = $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/edit", [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $newEmail,
                'name' => $newName,
                'plainPassword' => $newPassword,
                'organization' => $newOrganization->getId(),
                'locale' => $newLocale,
            ]
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        $otherUser->_refresh();
        $this->assertSame($newEmail, $otherUser->getEmail());
        $this->assertSame($newName, $otherUser->getName());
        $this->assertTrue($passwordHasher->isPasswordValid($otherUser->_real(), $newPassword));
        $this->assertSame($newOrganization->getId(), $otherUser->getOrganization()->getId());
        $this->assertSame($newLocale, $otherUser->getLocale());
    }

    public function testPostEditDoesNotChangePasswordIfEmpty(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $oldEmail = 'alix@example.com';
        $newEmail = 'benedict@example.com';
        $oldName = 'Alix Pataquès';
        $newName = 'Benedict Aphone';
        $oldPassword = 'secret';
        $newPassword = ''; // leave the password unchanged
        $otherUser = UserFactory::createOne([
            'email' => $oldEmail,
            'name' => $oldName,
            'password' => $oldPassword,
        ]);

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/edit", [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $newEmail,
                'name' => $newName,
                'plainPassword' => $newPassword,
                'locale' => 'en_GB',
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        $otherUser->_refresh();
        $this->assertSame($newEmail, $otherUser->getEmail());
        $this->assertSame($newName, $otherUser->getName());
        $this->assertTrue($passwordHasher->isPasswordValid($otherUser->_real(), $oldPassword));
    }

    public function testPostEditDoesNotChangeEmailNameOrPasswordIfLdapIsEnabled(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $oldEmail = 'alix@example.com';
        $newEmail = 'benedict@example.com';
        $oldName = 'Alix Pataquès';
        $newName = 'Benedict Aphone';
        $oldPassword = 'secret';
        $newPassword = 'super secret';
        $oldLdapIdentifier = 'alix';
        $newLdapIdentifier = 'benedict';
        $oldOrganization = OrganizationFactory::createOne();
        $newOrganization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne([
            'email' => $oldEmail,
            'name' => $oldName,
            'password' => $oldPassword,
            'organization' => $oldOrganization,
            'ldapIdentifier' => $oldLdapIdentifier,
        ]);
        // This is required so the organizations are accessible to the user.
        $this->grantOrga($otherUser->_real(), ['orga:create:tickets'], type: 'user');

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/edit", [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $newEmail,
                'name' => $newName,
                // Don't pass the password as Symfony Form will complain about extra field.
                // 'plainPassword' => $newPassword,
                'organization' => $newOrganization->getId(),
                'locale' => 'en_GB',
                'ldapIdentifier' => $newLdapIdentifier,
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        $otherUser->_refresh();
        $this->assertSame($oldEmail, $otherUser->getEmail());
        $this->assertSame($oldName, $otherUser->getName());
        $this->assertTrue($passwordHasher->isPasswordValid($otherUser->_real(), $oldPassword));
        $this->assertSame($newOrganization->getId(), $otherUser->getOrganization()->getId());
        $this->assertSame($newLdapIdentifier, $otherUser->getLdapIdentifier());
    }

    public function testPostEditAcceptsEmptyOrganization(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $oldEmail = 'alix@example.com';
        $newEmail = 'benedict@example.com';
        $oldName = 'Alix Pataquès';
        $newName = 'Benedict Aphone';
        $oldPassword = 'secret';
        $newPassword = 'super secret';
        $oldOrganization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne([
            'email' => $oldEmail,
            'name' => $oldName,
            'password' => $oldPassword,
            'organization' => $oldOrganization,
        ]);
        // This is required so the organizations are accessible to the user.
        $this->grantOrga($otherUser->_real(), ['orga:create:tickets'], type: 'user');

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/edit", [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $newEmail,
                'name' => $newName,
                'plainPassword' => $newPassword,
                'organization' => '',
                'locale' => 'en_GB',
            ],
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}", 302);
        $otherUser->_refresh();
        $this->assertSame($newEmail, $otherUser->getEmail());
        $this->assertSame($newName, $otherUser->getName());
        $this->assertTrue($passwordHasher->isPasswordValid($otherUser->_real(), $newPassword));
        $this->assertNull($otherUser->getOrganization());
    }

    public function testPostEditFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $oldEmail = 'alix@example.com';
        $newEmail = 'not an email'; // oops
        $oldName = 'Alix Pataquès';
        $newName = 'Benedict Aphone';
        $oldPassword = 'secret';
        $newPassword = 'super secret';
        $oldOrganization = OrganizationFactory::createOne();
        $newOrganization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne([
            'email' => $oldEmail,
            'name' => $oldName,
            'password' => $oldPassword,
            'organization' => $oldOrganization,
        ]);
        // This is required so the organizations are accessible to the user.
        $this->grantOrga($otherUser->_real(), ['orga:create:tickets'], type: 'user');

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/edit", [
            'user' => [
                '_token' => $this->generateCsrfToken($client, 'user'),
                'email' => $newEmail,
                'name' => $newName,
                'plainPassword' => $newPassword,
                'organization' => $newOrganization->getId(),
                'locale' => 'en_GB',
            ],
        ]);

        $this->assertSelectorTextContains('#user_email-error', 'Enter a valid email address');
        $this->clearEntityManager();
        $otherUser->_refresh();
        $this->assertSame($oldEmail, $otherUser->getEmail());
        $this->assertSame($oldName, $otherUser->getName());
        $this->assertTrue($passwordHasher->isPasswordValid($otherUser->_real(), $oldPassword));
        $this->assertSame($oldOrganization->getId(), $otherUser->getOrganization()->getId());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $oldEmail = 'alix@example.com';
        $newEmail = 'benedict@example.com';
        $oldName = 'Alix Pataquès';
        $newName = 'Benedict Aphone';
        $oldPassword = 'secret';
        $newPassword = 'super secret';
        $oldOrganization = OrganizationFactory::createOne();
        $newOrganization = OrganizationFactory::createOne();
        $otherUser = UserFactory::createOne([
            'email' => $oldEmail,
            'name' => $oldName,
            'password' => $oldPassword,
            'organization' => $oldOrganization,
        ]);
        // This is required so the organizations are accessible to the user.
        $this->grantOrga($otherUser->_real(), ['orga:create:tickets'], type: 'user');

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/edit", [
            'user' => [
                '_token' => 'not a token',
                'email' => $newEmail,
                'name' => $newName,
                'plainPassword' => $newPassword,
                'organization' => $newOrganization->getId(),
                'locale' => 'en_GB',
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#user-error', 'The security token is invalid');
        $otherUser->_refresh();
        $this->assertSame($oldEmail, $otherUser->getEmail());
        $this->assertSame($oldName, $otherUser->getName());
        $this->assertTrue($passwordHasher->isPasswordValid($otherUser->_real(), $oldPassword));
        $this->assertSame($oldOrganization->getId(), $otherUser->getOrganization()->getId());
    }

    public function testPostDeleteRemovesTheUserAndRedirects(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user'),
        ]);

        $this->assertResponseRedirects('/users', 302);
        UserFactory::assert()->notExists(['id' => $otherUser->getId()]);
    }

    public function testPostDeleteFailsIfUserIsTheLoggedInUser(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/users/{$currentUser->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user'),
        ]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($currentUser->_real());
        $this->grantAdmin($currentUser->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/deletion", [
            '_csrf_token' => 'not a token',
        ]);

        $this->assertResponseRedirects("/users/{$otherUser->getUid()}/edit", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        UserFactory::assert()->exists(['id' => $otherUser->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($currentUser->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/users/{$otherUser->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user'),
        ]);
    }
}
