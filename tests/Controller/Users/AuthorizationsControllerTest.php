<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Users;

use App\Entity\User;
use App\Entity\Role;
use App\Tests\AuthorizationHelper;
use App\Tests\SessionHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\TeamAuthorizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthorizationsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);

        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}/authorizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New authorization');
    }

    public function testGetNewCanRenderSuperAdminRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:*']);

        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}/authorizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#role', 'Super-admin');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/users/{$user->getUid()}/authorizations/new");
    }

    public function testPostCreateGrantsAdminAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);

        $this->assertSame(1, AuthorizationFactory::count());

        $client->request(Request::METHOD_GET, "/users/{$holder->getUid()}/authorizations/new");
        $crawler = $client->submitForm('form-create-authorization-submit', [
            'role' => $role->getUid(),
        ]);

        $this->assertSame(2, AuthorizationFactory::count());

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        $authorization = AuthorizationFactory::last();
        $this->assertSame($holder->getId(), $authorization->getHolder()->getId());
        $this->assertSame($role->getId(), $authorization->getRole()->getId());
    }

    public function testPostCreateGrantsOrgaAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $organization = OrganizationFactory::createOne();

        $this->assertSame(1, AuthorizationFactory::count());

        $client->request(Request::METHOD_GET, "/users/{$holder->getUid()}/authorizations/new");
        $crawler = $client->submitForm('form-create-authorization-submit', [
            'role' => $role->getUid(),
            'organization' => $organization->getUid(),
        ]);

        $this->assertSame(2, AuthorizationFactory::count());

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        $authorization = AuthorizationFactory::last();
        $this->assertSame($holder->getId(), $authorization->getHolder()->getId());
        $this->assertSame($role->getId(), $authorization->getRole()->getId());
        $this->assertSame($organization->getId(), $authorization->getOrganization()->getId());
    }

    public function testPostCreateCanGrantSuperAuthorizationIfCorrectAuthorization(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:*']);
        $holder = UserFactory::createOne();
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var \App\Repository\RoleRepository $roleRepository */
        $roleRepository = $entityManager->getRepository(Role::class);
        $superRole = $roleRepository->findOrCreateSuperRole();

        $client->request(Request::METHOD_POST, "/users/{$holder->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $superRole->getUid(),
        ]);

        $this->assertSame(2, AuthorizationFactory::count());

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        $authorization = AuthorizationFactory::last();
        $this->assertSame($holder->getId(), $authorization->getHolder()->getId());
        $this->assertSame($superRole->getId(), $authorization->getRole()->getId());
    }

    public function testPostCreateFailsIfRoleDoesNotExist(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();

        $client->request(Request::METHOD_POST, "/users/{$holder->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => 'not a uid',
        ]);

        $this->assertSelectorTextContains('#role-error', 'Select a role from the list');
        $this->assertSame(1, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfSuperRoleAndNotCorrectAuthorization(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var \App\Repository\RoleRepository $roleRepository */
        $roleRepository = $entityManager->getRepository(Role::class);
        $superRole = $roleRepository->findOrCreateSuperRole();

        $client->request(Request::METHOD_POST, "/users/{$holder->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $superRole->getUid(),
        ]);

        $this->assertSelectorTextContains('#role-error', 'You are not allowed to grant super-admin authorization');
        $this->assertSame(1, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);

        $client->request(Request::METHOD_POST, "/users/{$holder->getUid()}/authorizations/new", [
            '_csrf_token' => 'not a token',
            'role' => $role->getUid(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $this->assertSame(1, AuthorizationFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/users/{$holder->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create user authorization'),
            'role' => $role->getUid(),
        ]);
    }

    public function testPostDeleteDeletesAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'holder' => $holder,
            'role' => $role,
        ]);

        $client->request(Request::METHOD_POST, "/authorizations/{$authorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user authorization'),
        ]);

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteCanDeleteSuperRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:*']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'super',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'holder' => $holder,
            'role' => $role,
        ]);

        $client->request(Request::METHOD_POST, "/authorizations/{$authorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user authorization'),
        ]);

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfSuperRoleAndNotCorrectAuthorization(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'super',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'holder' => $holder,
            'role' => $role,
        ]);

        $client->request(Request::METHOD_POST, "/authorizations/{$authorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user authorization'),
        ]);

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains(
            '#notifications',
            'You are not allowed to revoke this super-admin authorization',
        );
        AuthorizationFactory::assert()->exists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfSuperRoleAndCurrentUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:*']);
        $authorization = AuthorizationFactory::last();

        $client->request(Request::METHOD_POST, "/authorizations/{$authorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user authorization'),
        ]);

        $this->assertResponseRedirects("/users/{$user->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains(
            '#notifications',
            'You are not allowed to revoke this super-admin authorization',
        );
        AuthorizationFactory::assert()->exists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfAuthorizationIsManagedByTeam(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);
        $teamAuthorization = TeamAuthorizationFactory::createOne();
        $authorization = AuthorizationFactory::createOne([
            'holder' => $holder,
            'role' => $role,
            'teamAuthorization' => $teamAuthorization,
        ]);

        $client->request(Request::METHOD_POST, "/authorizations/{$authorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user authorization'),
        ]);

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains(
            '#notifications',
            'You are not allowed to revoke a team authorization',
        );
        AuthorizationFactory::assert()->exists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:users']);
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'holder' => $holder,
            'role' => $role,
        ]);

        $client->request(Request::METHOD_POST, "/authorizations/{$authorization->getUid()}/deletion", [
            '_csrf_token' => 'not a token',
        ]);

        $this->assertResponseRedirects("/users/{$holder->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        AuthorizationFactory::assert()->exists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $holder = UserFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'admin',
        ]);
        $authorization = AuthorizationFactory::createOne([
            'holder' => $holder,
            'role' => $role,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/authorizations/{$authorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete user authorization'),
        ]);
    }
}
