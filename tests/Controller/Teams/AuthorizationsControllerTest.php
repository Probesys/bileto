<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Teams;

use App\Tests\AuthorizationHelper;
use App\Tests\FactoriesHelper;
use App\Tests\SessionHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TeamAuthorizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthorizationsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/authorizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New authorization');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/authorizations/new");
    }

    public function testPostCreateGrantsAdminAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $organization = OrganizationFactory::createOne();

        $this->assertSame(0, TeamAuthorizationFactory::count());

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
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
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $this->assertSame(0, TeamAuthorizationFactory::count());

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
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
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => $role->getUid(),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $agent->_refresh();
        $authorizations = $agent->getAuthorizations();
        $teamAuthorization = TeamAuthorizationFactory::last();
        $this->assertSame(1, count($authorizations));
        $this->assertSame($teamAuthorization->getId(), $authorizations[0]->getTeamAuthorization()->getId());
    }

    public function testPostCreateFailsIfRoleDoesNotExist(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
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
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => Foundry\faker()->randomElement(['user', 'admin', 'super']),
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
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
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
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
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne();
        $role = RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create team authorization'),
            'role' => $role->getUid(),
        ]);
    }

    public function testPostDeleteDeletesTeamAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);
        $authorization = AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteUnsetsResponsibleTeamIfTeamHasNoLongerAccessToOrganization(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $organization = OrganizationFactory::createOne([
            'responsibleTeam' => $team,
        ]);
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $authorization = AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
            'organization' => $organization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
        $organization->_refresh();
        $this->assertNull($organization->getResponsibleTeam());
    }

    public function testPostDeleteDoesNotChangeResponsibleTeamIfTeamStillHasAccessToOrganization(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $organization = OrganizationFactory::createOne([
            'responsibleTeam' => $team,
        ]);
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $teamAuthorization2 = TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $authorization = AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
            'organization' => $organization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
        $organization->_refresh();
        $this->assertNotNull($organization->getResponsibleTeam());
    }

    public function testPostDeleteDoesNotChangeResponsibleTeamIfTeamHasGlobalAccess(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $organization = OrganizationFactory::createOne([
            'responsibleTeam' => $team,
        ]);
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $teamAuthorization2 = TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => null,
        ]);
        $authorization = AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
            'organization' => $organization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
        $organization->_refresh();
        $this->assertNotNull($organization->getResponsibleTeam());
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
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
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne();
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);
    }
}
