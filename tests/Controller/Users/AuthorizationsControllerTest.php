<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Users;

use App\Entity\User;
use App\Entity\Role;
use App\Tests\AuthorizationHelper;
use App\Tests\SessionHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthorizationsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsAuthorizationsSortedByRolesAndOrgasNames(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $roleA = RoleFactory::createOne([
            'name' => 'Role A',
            'type' => 'admin',
            'permissions' => ['admin:manage:users'],
        ]);
        $roleB = RoleFactory::createOne([
            'name' => 'Role B',
            'type' => 'orga',
        ]);
        $orgaA = OrganizationFactory::createOne([
            'name' => 'Orga A',
        ]);
        $orgaB = OrganizationFactory::createOne([
            'name' => 'Orga B',
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleB,
            'organization' => $orgaB,
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleB,
            'organization' => $orgaA,
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleB,
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleA,
        ]);

        $client->request('GET', "/users/{$user->getUid()}/authorizations");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Authorizations');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(2)', 'Role A');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(3)', 'Role B * global *');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(4)', 'Role B Orga A');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(5)', 'Role B Orga B');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', "/users/{$user->getUid()}/authorizations");
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);

        $client->request('GET', "/users/{$user->getUid()}/authorizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New authorization');
    }

    public function testGetNewCanRenderSuperAdminRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:*']);

        $client->request('GET', "/users/{$user->getUid()}/authorizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#role', 'Super-admin');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', "/users/{$user->getUid()}/authorizations/new");
    }

    public function testPostCreateGrantsAdminAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:manage:users']);
        $user = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);

        $this->assertSame(1, AuthorizationFactory::count());

        $client->request('GET', "/users/{$user->getUid()}/authorizations/new");
        $crawler = $client->submitForm('form-create-authorization-submit', [
            'role' => $role->getUid(),
        ]);

        $this->assertSame(2, AuthorizationFactory::count());

        $this->assertResponseRedirects("/users/{$user->getUid()}/authorizations", 302);
        $authorization = AuthorizationFactory::last();
        $this->assertSame($user->getId(), $authorization->getHolder()->getId());
        $this->assertSame($role->getId(), $authorization->getRole()->getId());
    }

    public function testPostCreateGrantsOrgaAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:manage:users']);
        $user = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'orga',
        ]);
        $organization = OrganizationFactory::createOne();

        $this->assertSame(1, AuthorizationFactory::count());

        $client->request('GET', "/users/{$user->getUid()}/authorizations/new");
        $crawler = $client->submitForm('form-create-authorization-submit', [
            'role' => $role->getUid(),
            'organization' => $organization->getUid(),
        ]);

        $this->assertSame(2, AuthorizationFactory::count());

        $this->assertResponseRedirects("/users/{$user->getUid()}/authorizations", 302);
        $authorization = AuthorizationFactory::last();
        $this->assertSame($user->getId(), $authorization->getHolder()->getId());
        $this->assertSame($role->getId(), $authorization->getRole()->getId());
        $this->assertSame($organization->getId(), $authorization->getOrganization()->getId());
    }

    public function testPostCreateCanGrantSuperAuthorizationIfCorrectAuthorization(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:*']);
        $user = UserFactory::createOne();
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var \App\Repository\RoleRepository $roleRepository */
        $roleRepository = $entityManager->getRepository(Role::class);
        $superRole = $roleRepository->findOrCreateSuperRole();

        $client->request('POST', "/users/{$user->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $superRole->getUid(),
        ]);

        $this->assertSame(2, AuthorizationFactory::count());

        $this->assertResponseRedirects("/users/{$user->getUid()}/authorizations", 302);
        $authorization = AuthorizationFactory::last();
        $this->assertSame($user->getId(), $authorization->getHolder()->getId());
        $this->assertSame($superRole->getId(), $authorization->getRole()->getId());
    }

    public function testPostCreateFailsIfRoleDoesNotExist(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:manage:users']);
        $user = UserFactory::createOne();

        $client->request('POST', "/users/{$user->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => 'not a uid',
        ]);

        $this->assertSelectorTextContains('#role-error', 'Choose a role from the list.');
        $this->assertSame(1, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfSuperRoleAndNotCorrectAuthorization(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:manage:users']);
        $user = UserFactory::createOne();
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var \App\Repository\RoleRepository $roleRepository */
        $roleRepository = $entityManager->getRepository(Role::class);
        $superRole = $roleRepository->findOrCreateSuperRole();

        $client->request('POST', "/users/{$user->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $superRole->getUid(),
        ]);

        $this->assertSelectorTextContains('#role-error', 'You can’t grant super-admin authorization.');
        $this->assertSame(1, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfUserHasAlreadyAnAdminRole(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:manage:users']);
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);

        $client->request('POST', "/users/{$currentUser->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $role->getUid(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'This user already has an admin role.');
        $this->assertSame(1, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfUserHasAlreadyAnOrgaRoleOnTheGivenOrganization(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:manage:users']);
        $role = RoleFactory::createOne([
            'type' => 'orga',
        ]);
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($currentUser->object(), ['orga:see'], $organization->object());

        $client->request('POST', "/users/{$currentUser->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $role->getUid(),
            'organization' => $organization->getUid(),
        ]);

        $this->assertSelectorTextContains(
            '[data-test="alert-error"]',
            'This user already has an orga role for this organization.'
        );
        $this->assertSame(2, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $this->grantAdmin($currentUser->object(), ['admin:manage:users']);
        $user = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);

        $client->request('POST', "/users/{$user->getUid()}/authorizations/new", [
            '_csrf_token' => 'not a token',
            'role' => $role->getUid(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $this->assertSame(1, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);
        $client = static::createClient();
        $currentUser = UserFactory::createOne();
        $client->loginUser($currentUser->object());
        $user = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/users/{$user->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $role->getUid(),
        ]);
    }
}
