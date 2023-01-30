<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RolesControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsRolesSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
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
        $this->grantAdmin($user->object(), ['admin:manage:roles']);

        $this->assertSame(0, count(RoleFactory::findBy(['type' => 'super'])));

        $client->request('GET', '/roles');

        $this->assertSame(1, count(RoleFactory::findBy(['type' => 'super'])));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="role-super-item"]', 'Super-admin');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/roles');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);

        $client->request('GET', '/roles/new?type=orga');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New role');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/roles/new?type=orga');
    }

    public function testPostCreateCreatesARoleAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request('GET', '/roles/new?type=orga');
        $crawler = $client->submitForm('form-save-role-submit', [
            'name' => $name,
            'description' => $description,
        ]);

        Time::unfreeze();

        $this->assertSame(2, RoleFactory::count());

        $this->assertResponseRedirects('/roles', 302);
        $role = RoleFactory::last();
        $this->assertSame($name, $role->getName());
        $this->assertSame($description, $role->getDescription());
        // This permission is always set for admin roles
        $this->assertSame(['orga:see'], $role->getPermissions());
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
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
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
        $this->assertSame(['admin:see'], $role->getPermissions());
    }

    public function testPostCreateCannotCreateSuperRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
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
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
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
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        $permissions = [
            'admin:*',
            'admin:see',
            'orga:see',
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
        $this->assertSame(['orga:see'], $role->getPermissions());
    }

    public function testPostCreateSanitizesPermissionsForAdminRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        $permissions = [
            'admin:*',
            'admin:see',
            'orga:see',
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
        $this->assertSame(['admin:see'], $role->getPermissions());
    }

    public function testPostCreateFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = '';
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name is required.');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfNameIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = str_repeat('a', 51);
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name must be 50 characters maximum.');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfDescriptionIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = 'My role';
        $description = '';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#description-error', 'The description is required.');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfDescriptionIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = 'My role';
        $description = str_repeat('a', 256);

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#description-error', 'The description must be 255 characters maximum.');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfNameAlreadyExists(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        RoleFactory::createOne([
            'name' => $name,
        ]);

        $this->assertSame(2, RoleFactory::count());

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The role "My role" is already used.');
        $this->assertSame(2, RoleFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';

        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => 'not a token',
            'name' => $name,
            'description' => $description,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My role';
        $description = 'What it does';

        $client->catchExceptions(false);
        $client->request('POST', '/roles/new?type=orga', [
            '_csrf_token' => $this->generateCsrfToken($client, 'create role'),
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $role = RoleFactory::createOne([
            'type' => RoleFactory::faker()->randomElement(['admin', 'orga']),
        ]);

        $client->request('GET', "/roles/{$role->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit role');
    }

    public function testGetEditFailsIfTypeIsSuper(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $role = RoleFactory::createOne([
            'type' => 'super',
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/roles/{$role->getUid()}/edit");
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $role = RoleFactory::createOne([
            'type' => RoleFactory::faker()->randomElement(['admin', 'orga']),
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/roles/{$role->getUid()}/edit");
    }

    public function testPostUpdateChangesTheRoleAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $type = RoleFactory::faker()->randomElement(['admin', 'orga']);
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:manage:organizations'];
            $newPermissions = ['admin:manage:roles'];
        } else {
            $oldPermissions = ['orga:create:tickets'];
            $newPermissions = ['orga:create:tickets:messages'];
        }
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->request('POST', "/roles/{$role->getUid()}/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update role'),
            'name' => $newName,
            'description' => $newDescription,
            'permissions' => $newPermissions,
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role->refresh();
        $this->assertSame($newName, $role->getName());
        $this->assertSame($newDescription, $role->getDescription());
        if ($type === 'admin') {
            $newPermissions[] = 'admin:see';
        } else {
            $newPermissions[] = 'orga:see';
        }
        $this->assertEquals($newPermissions, $role->getPermissions());
    }

    public function testPostUpdateSanitizesThePermissions(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $type = 'admin';
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        $oldPermissions = ['admin:manage:organizations'];
        $newPermissions = ['admin:*'];
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->request('POST', "/roles/{$role->getUid()}/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update role'),
            'name' => $newName,
            'description' => $newDescription,
            'permissions' => $newPermissions,
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role->refresh();
        $this->assertSame($newName, $role->getName());
        $this->assertSame($newDescription, $role->getDescription());
        $this->assertEquals(['admin:see'], $role->getPermissions());
    }

    public function testPostUpdateFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $type = RoleFactory::faker()->randomElement(['admin', 'orga']);
        $oldName = 'Old role';
        $newName = '';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:manage:organizations'];
            $newPermissions = ['admin:manage:roles'];
        } else {
            $oldPermissions = ['orga:create:tickets'];
            $newPermissions = ['orga:create:tickets:messages'];
        }
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->request('POST', "/roles/{$role->getUid()}/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update role'),
            'name' => $newName,
            'description' => $newDescription,
            'permissions' => $newPermissions,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name is required.');
        $role->refresh();
        $this->assertSame($oldName, $role->getName());
        $this->assertSame($oldDescription, $role->getDescription());
        $this->assertEquals($oldPermissions, $role->getPermissions());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $type = RoleFactory::faker()->randomElement(['admin', 'orga']);
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:manage:organizations'];
            $newPermissions = ['admin:manage:roles'];
        } else {
            $oldPermissions = ['orga:create:tickets'];
            $newPermissions = ['orga:create:tickets:messages'];
        }
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->request('POST', "/roles/{$role->getUid()}/edit", [
            '_csrf_token' => 'not a token',
            'name' => $newName,
            'description' => $newDescription,
            'permissions' => $newPermissions,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $role->refresh();
        $this->assertSame($oldName, $role->getName());
        $this->assertSame($oldDescription, $role->getDescription());
        $this->assertEquals($oldPermissions, $role->getPermissions());
    }

    public function testPostUpdateFailsIfTypeIsSuper(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:roles']);
        $type = 'super';
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        $oldPermissions = ['admin:*'];
        $newPermissions = ['admin:manage:roles'];
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/roles/{$role->getUid()}/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update role'),
            'name' => $newName,
            'description' => $newDescription,
            'permissions' => $newPermissions,
        ]);
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $type = RoleFactory::faker()->randomElement(['admin', 'orga']);
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:manage:organizations'];
            $newPermissions = ['admin:manage:roles'];
        } else {
            $oldPermissions = ['orga:create:tickets'];
            $newPermissions = ['orga:create:tickets:messages'];
        }
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/roles/{$role->getUid()}/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update role'),
            'name' => $newName,
            'description' => $newDescription,
            'permissions' => $newPermissions,
        ]);
    }
}
