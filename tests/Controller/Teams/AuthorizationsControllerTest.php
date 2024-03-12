<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Teams;

use App\Tests\AuthorizationHelper;
use App\Tests\SessionHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TeamAuthorizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Factory;
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
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request('GET', "/teams/{$team->getUid()}/authorizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New authorization');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $team = TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/teams/{$team->getUid()}/authorizations/new");
    }

    public function testPostCreateGrantsAdminAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $organization = OrganizationFactory::createOne();

        $this->assertSame(0, TeamAuthorizationFactory::count());

        $client->request('POST', "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => $role->getUid(),
            'organization' => $organization->getUid(),
        ]);

        $this->assertSame(1, TeamAuthorizationFactory::count());

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $teamAuthorization = TeamAuthorizationFactory::last();
        $this->assertSame($team->getId(), $teamAuthorization->getTeam()->getId());
        $this->assertSame($role->getId(), $teamAuthorization->getRole()->getId());
        $this->assertSame($organization->getId(), $teamAuthorization->getOrganization()->getId());
    }

    public function testPostCreateGrantsAgentAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $this->assertSame(0, TeamAuthorizationFactory::count());

        $client->request('POST', "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => $role->getUid(),
        ]);

        $this->assertSame(1, TeamAuthorizationFactory::count());

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $teamAuthorization = TeamAuthorizationFactory::last();
        $this->assertSame($team->getId(), $teamAuthorization->getTeam()->getId());
        $this->assertSame($role->getId(), $teamAuthorization->getRole()->getId());
    }

    public function testPostCreateGrantsTeamAuthorizationsToTheAgents(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->request('POST', "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => $role->getUid(),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $agent->refresh();
        $authorizations = $agent->getAuthorizations();
        $teamAuthorization = TeamAuthorizationFactory::last();
        $this->assertSame(1, count($authorizations));
        $this->assertSame($teamAuthorization->getId(), $authorizations[0]->getTeamAuthorization()->getId());
    }

    public function testPostCreateFailsIfRoleDoesNotExist(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request('POST', "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => 'not a uid',
        ]);

        $this->assertSelectorTextContains('#role-error', 'Select a role from the list');
        $this->assertSame(0, TeamAuthorizationFactory::count());
    }

    public function testPostCreateFailsIfNotAgentRole(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => Factory::faker()->randomElement(['user', 'admin', 'super']),
        ]);

        $client->request('POST', "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => $role->getUid(),
        ]);

        $this->assertSelectorTextContains('#role-error', 'Select a role from the list');
        $this->assertSame(0, TeamAuthorizationFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->request('POST', "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => 'not a token',
            'role' => $role->getUid(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $this->assertSame(0, TeamAuthorizationFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => $role->getUid(),
        ]);
    }

    public function testPostDeleteDeletesTeamAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);
        $authorization = AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
        ]);

        // We need to clear the entities or they will stay in memory. An option
        // would be to set `cascade: ['remove']` on the Organization relations,
        // but it would decrease the performance for no interest since we don't
        // need it outside of the tests.
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $doctrine = self::getContainer()->get('doctrine');
        $entityManager = $doctrine->getManager();
        $entityManager->clear();

        $client->request('POST', "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);

        $client->request('POST', "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => 'not a token',
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        TeamAuthorizationFactory::assert()->exists(['id' => $teamAuthorization->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $team = TeamFactory::createOne();
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);
    }
}
