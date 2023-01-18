<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RolesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsRolesSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        RoleFactory::createOne([
            'name' => 'foo',
            'type' => 'orga',
        ]);
        RoleFactory::createOne([
            'name' => 'bar',
            'type' => 'orga',
        ]);

        $client->request('GET', '/roles');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Roles');
        $this->assertSelectorTextContains('[data-test="role-orga-item"]:nth-child(2)', 'bar');
        $this->assertSelectorTextContains('[data-test="role-orga-item"]:nth-child(3)', 'foo');
    }

    public function testGetIndexCreatesAutomaticallyTheSuperRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $this->assertSame(0, count(RoleFactory::all()));

        $client->request('GET', '/roles');

        $this->assertSame(1, count(RoleFactory::findBy(['type' => 'super'])));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="role-admin-item"]:nth-child(2)', 'Super-admin');
    }

    public function testGetIndexRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/roles');

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->request('GET', '/roles/new?type=orga');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New role');
    }

    public function testGetNewRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/roles/new?type=orga');

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testPostCreateCreatesARoleAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';

        $client->request('GET', '/roles/new?type=orga');
        $crawler = $client->submitForm('form-create-role-submit', [
            'name' => $name,
            'description' => $description,
        ]);

        Time::unfreeze();

        $this->assertResponseRedirects('/roles', 302);
        $role = RoleFactory::last();
        $this->assertSame($name, $role->getName());
        $this->assertSame($description, $role->getDescription());
        $this->assertSame([], $role->getPermissions());
        $this->assertSame('orga', $role->getType());
        $this->assertSame(20, strlen($role->getUid()));
        $this->assertEquals($now, $role->getCreatedAt());
        $this->assertSame($user->getId(), $role->getCreatedBy()->getId());
    }

    public function testPostCreateCanCreateAdminRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=admin', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role = RoleFactory::last();
        $this->assertSame('admin', $role->getType());
        // This permission is always set for admin roles
        $this->assertSame(['admin:see:settings'], $role->getPermissions());
    }

    public function testPostCreateCannotCreateSuperRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=super', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role = RoleFactory::last();
        $this->assertSame('orga', $role->getType());
    }

    public function testPostCreateCreatesAnOrgaRoleByDefault(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';

        $client->request('POST', '/roles/new', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role = RoleFactory::last();
        $this->assertSame('orga', $role->getType());
    }

    public function testPostCreateSanitizesPermissionsForOrgaRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';
        $permissions = [
            'admin:*',
            'admin:see:settings',
            'orga:foo',
            'foo:bar',
        ];

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
            'permissions' => $permissions,
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role = RoleFactory::last();
        $this->assertSame([], $role->getPermissions());
    }

    public function testPostCreateSanitizesPermissionsForAdminRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';
        $permissions = [
            'admin:*',
            'admin:see:settings',
            'orga:foo',
            'foo:bar',
        ];

        $client->request('POST', '/roles/new?type=admin', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
            'permissions' => $permissions,
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role = RoleFactory::last();
        $this->assertSame(['admin:see:settings'], $role->getPermissions());
    }

    public function testPostCreateFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = '';
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name is required.');
        $this->assertSame(0, RoleFactory::count());
    }

    public function testPostCreateFailsIfNameIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = str_repeat('a', 51);
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name must be 50 characters maximum.');
        $this->assertSame(0, RoleFactory::count());
    }

    public function testPostCreateFailsIfDescriptionIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = '';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#description-error', 'The description is required.');
        $this->assertSame(0, RoleFactory::count());
    }

    public function testPostCreateFailsIfDescriptionIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = str_repeat('a', 256);

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#description-error', 'The description must be 255 characters maximum.');
        $this->assertSame(0, RoleFactory::count());
    }

    public function testPostCreateFailsIfNameAlreadyExists(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';
        RoleFactory::createOne([
            'name' => $name,
        ]);

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The role "My role" is already used.');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => 'not a token',
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $this->assertSame(0, RoleFactory::count());
    }
}
