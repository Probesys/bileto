<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Role;
use App\Tests\AuthorizationHelper;
use App\Tests\FactoriesHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RolesControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsRolesSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        RoleFactory::createOne([
            'name' => 'foo',
            'type' => 'agent',
        ]);
        RoleFactory::createOne([
            'name' => 'bar',
            'type' => 'agent',
        ]);

        $client->request(Request::METHOD_GET, '/roles');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Roles');
        $this->assertSelectorTextContains('[data-test="role-agent-item"]:nth-child(1)', 'bar');
        $this->assertSelectorTextContains('[data-test="role-agent-item"]:nth-child(2)', 'foo');
    }

    public function testGetIndexCreatesAutomaticallyTheSuperRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);

        $this->assertSame(0, count(RoleFactory::findBy(['type' => 'super'])));

        $client->request(Request::METHOD_GET, '/roles');

        $this->assertSame(1, count(RoleFactory::findBy(['type' => 'super'])));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="role-super-item"]', 'Super-admin');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/roles');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);

        $client->request(Request::METHOD_GET, '/roles/new?type=agent');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New agent role');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/roles/new?type=agent');
    }

    public function testPostCreateCreatesARoleAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'agent',
            ],
        ]);

        Time::unfreeze();

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(2, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertSame($name, $role->getName());
        $this->assertSame($description, $role->getDescription());
        // This permission is always set for orga roles
        $this->assertSame(['orga:see'], $role->getPermissions());
        $this->assertSame('agent', $role->getType());
        $this->assertSame(20, strlen($role->getUid()));
        $this->assertEquals($now, $role->getCreatedAt());
        $this->assertSame($user->getId(), $role->getCreatedBy()->getId());
    }

    public function testPostCreateCanCreateAdminRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'admin',
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(2, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertSame('admin', $role->getType());
        // This permission is always set for admin roles
        $this->assertSame(['admin:see'], $role->getPermissions());
    }

    public function testPostCreateCannotCreateSuperRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'super',
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(2, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertSame('user', $role->getType());
    }

    public function testPostCreateCreatesAnOrgaUserRoleByDefault(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(2, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertSame('user', $role->getType());
    }

    public function testPostCreateCanCreateDefaultUserRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        $initialDefaultRole = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);

        $this->assertSame(2, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'user',
                'isDefault' => true,
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(3, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertTrue($role->isDefault());
        $initialDefaultRole->_refresh();
        $this->assertFalse($initialDefaultRole->isDefault());
    }

    public function testPostCreateSanitizesPermissionsForAgentRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        $permissions = [
            'admin:*',
            'admin:see',
            'orga:see',
            'orga:foo',
            'foo:bar',
        ];

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'agent',
                'permissions' => $permissions,
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(2, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertSame(['orga:see'], $role->getPermissions());
    }

    public function testPostCreateSanitizesPermissionsForUserRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        $permissions = [
            'admin:*',
            'admin:see',
            'orga:see',
            'orga:create:tickets:messages:confidential',
            'orga:foo',
            'foo:bar',
        ];

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'user',
                'permissions' => $permissions,
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(2, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertSame(['orga:see'], $role->getPermissions());
    }

    public function testPostCreateSanitizesPermissionsForAdminRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        $permissions = [
            'admin:*',
            'admin:see',
            'orga:see',
            'orga:foo',
            'foo:bar',
        ];

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'admin',
                'permissions' => $permissions,
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $this->assertSame(2, RoleFactory::count());
        $role = RoleFactory::last();
        $this->assertSame(['admin:see'], $role->getPermissions());
    }

    public function testPostCreateFailsWhenSettingDefaultAgentRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        $initialDefaultRole = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);

        $this->assertSame(2, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
                'type' => 'agent',
                'isDefault' => true,
            ],
        ]);

        $this->clearEntityManager();

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame(2, RoleFactory::count());
        $initialDefaultRole->_refresh();
        $this->assertTrue($initialDefaultRole->isDefault());
    }

    public function testPostCreateFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = '';
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
            ],
        ]);

        $this->assertSelectorTextContains('#role_name-error', 'Enter a name');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfNameIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = str_repeat('a', 51);
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
            ],
        ]);

        $this->assertSelectorTextContains('#role_name-error', 'Enter a name of less than 50 characters');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfDescriptionIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = '';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
            ],
        ]);

        $this->assertSelectorTextContains('#role_description-error', 'Enter a description');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfDescriptionIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = str_repeat('a', 256);

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
            ],
        ]);

        $this->assertSelectorTextContains('#role_description-error', 'Enter a description of less than 255 characters');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfNameAlreadyExists(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';
        RoleFactory::createOne([
            'name' => $name,
        ]);

        $this->assertSame(2, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
            ],
        ]);

        $this->assertSelectorTextContains('#role_name-error', 'Enter a different name, a role already has this name');
        $this->assertSame(2, RoleFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $name = 'My role';
        $description = 'What it does';

        $this->assertSame(1, RoleFactory::count());

        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => 'not a token',
                'name' => $name,
                'description' => $description,
                'type' => 'admin',
            ],
        ]);

        $this->assertSelectorTextContains('#role-error', 'The security token is invalid');
        $this->assertSame(1, RoleFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $name = 'My role';
        $description = 'What it does';

        $this->assertSame(0, RoleFactory::count());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, '/roles/new', [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $name,
                'description' => $description,
            ],
        ]);

        $this->assertSame(0, RoleFactory::count());
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $type = Foundry\faker()->randomElement(['admin', 'agent', 'user']);
        $role = RoleFactory::createOne([
            'type' => $type,
        ]);

        $client->request(Request::METHOD_GET, "/roles/{$role->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        if ($type === 'admin') {
            $this->assertSelectorTextContains('h1', 'Edit administrator role');
        } elseif ($type === 'agent') {
            $this->assertSelectorTextContains('h1', 'Edit agent role');
        } else {
            $this->assertSelectorTextContains('h1', 'Edit user role');
        }
    }

    public function testGetEditFailsIfTypeIsSuper(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $role = RoleFactory::createOne([
            'type' => 'super',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/roles/{$role->getUid()}/edit");
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $role = RoleFactory::createOne([
            'type' => Foundry\faker()->randomElement(['admin', 'agent', 'user']),
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/roles/{$role->getUid()}/edit");
    }

    public function testPostUpdateChangesTheRoleAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $type = Foundry\faker()->randomElement(['admin', 'agent', 'user']);
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:create:organizations'];
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

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $newName,
                'description' => $newDescription,
                'permissions' => $newPermissions,
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role->_refresh();
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
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $type = 'admin';
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        $oldPermissions = ['admin:create:organizations'];
        $newPermissions = ['admin:*'];
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $newName,
                'description' => $newDescription,
                'permissions' => $newPermissions,
            ],
        ]);

        $this->assertResponseRedirects('/roles', 302);
        $role->_refresh();
        $this->assertSame($newName, $role->getName());
        $this->assertSame($newDescription, $role->getDescription());
        $this->assertEquals(['admin:see'], $role->getPermissions());
    }

    public function testPostUpdateCanSetDefaultRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $initialDefaultRole = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);
        $role = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => false,
        ]);

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
                'permissions' => $role->getPermissions(),
                'isDefault' => true,
            ],
        ]);

        $this->clearEntityManager();

        $this->assertResponseRedirects('/roles', 302);
        $role->_refresh();
        $this->assertTrue($role->isDefault());
        $initialDefaultRole->_refresh();
        $this->assertFalse($initialDefaultRole->isDefault());
    }

    public function testPostUpdateFailsWhenSettingDefaultForAgentRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $initialDefaultRole = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);
        $role = RoleFactory::createOne([
            'type' => 'agent',
            'isDefault' => false,
        ]);

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $role->getName(),
                'description' => $role->getDescription(),
                'permissions' => $role->getPermissions(),
                'isDefault' => true,
            ],
        ]);

        $this->clearEntityManager();

        $this->assertResponseStatusCodeSame(400);
        $role->_refresh();
        $this->assertFalse($role->isDefault());
        $initialDefaultRole->_refresh();
        $this->assertTrue($initialDefaultRole->isDefault());
    }

    public function testPostUpdateFailsIfParamsAreInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $type = Foundry\faker()->randomElement(['admin', 'agent', 'user']);
        $oldName = 'Old role';
        $newName = '';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:create:organizations', 'admin:see'];
            $newPermissions = ['admin:manage:roles', 'admin:see'];
        } else {
            $oldPermissions = ['orga:create:tickets', 'orga:see'];
            $newPermissions = ['orga:create:tickets:messages', 'orga:see'];
        }
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $newName,
                'description' => $newDescription,
                'permissions' => $newPermissions,
            ],
        ]);

        $this->assertSelectorTextContains('#role_name-error', 'Enter a name');
        $this->clearEntityManager();
        $role->_refresh();
        $this->assertSame($oldName, $role->getName());
        $this->assertSame($oldDescription, $role->getDescription());
        $this->assertEquals($oldPermissions, $role->getPermissions());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $type = Foundry\faker()->randomElement(['admin', 'agent', 'user']);
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:create:organizations', 'admin:see'];
            $newPermissions = ['admin:manage:roles', 'admin:see'];
        } else {
            $oldPermissions = ['orga:create:tickets', 'orga:see'];
            $newPermissions = ['orga:create:tickets:messages', 'orga:see'];
        }
        $role = RoleFactory::createOne([
            'type' => $type,
            'name' => $oldName,
            'description' => $oldDescription,
            'permissions' => $oldPermissions,
        ]);

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => 'not a token',
                'name' => $newName,
                'description' => $newDescription,
                'permissions' => $newPermissions,
            ],
        ]);

        $this->assertSelectorTextContains('#role-error', 'The security token is invalid');
        $this->clearEntityManager();
        $role->_refresh();
        $this->assertSame($oldName, $role->getName());
        $this->assertSame($oldDescription, $role->getDescription());
        $this->assertEquals($oldPermissions, $role->getPermissions());
    }

    public function testPostUpdateFailsIfTypeIsSuper(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
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
        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $newName,
                'description' => $newDescription,
                'permissions' => $newPermissions,
            ],
        ]);
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $type = Foundry\faker()->randomElement(['admin', 'agent', 'user']);
        $oldName = 'Old role';
        $newName = 'New role';
        $oldDescription = 'Description of the old role';
        $newDescription = 'Description of the new role';
        if ($type === 'admin') {
            $oldPermissions = ['admin:create:organizations'];
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
        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/edit", [
            'role' => [
                '_token' => $this->generateCsrfToken($client, 'role'),
                'name' => $newName,
                'description' => $newDescription,
                'permissions' => $newPermissions,
            ],
        ]);
    }

    public function testPostDeleteRemovesTheRoleAndAssociatedAuthorizationsAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'role' => $role,
        ]);

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete role'),
        ]);

        $this->assertResponseRedirects('/roles', 302);
        RoleFactory::assert()->notExists(['id' => $role->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'role' => $role,
        ]);

        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertResponseRedirects("/roles/{$role->getUid()}/edit", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        RoleFactory::assert()->exists(['id' => $role->getId()]);
        AuthorizationFactory::assert()->exists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfTypeIsSuper(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:roles']);
        $role = RoleFactory::createOne([
            'type' => 'super',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete role'),
        ]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'role' => $role,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/roles/{$role->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete role'),
        ]);
    }
}
