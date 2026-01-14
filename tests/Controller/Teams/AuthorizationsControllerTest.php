<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Teams;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthorizationsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();

        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/authorizations/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New authorization');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = Factory\TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/authorizations/new");
    }

    public function testPostNewGrantsAuthorizationOnOrganizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $role = Factory\RoleFactory::createOne([
            'type' => 'agent',
        ]);
        $organization = Factory\OrganizationFactory::createOne();

        $this->assertSame(0, Factory\TeamAuthorizationFactory::count());

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            'authorization' => [
                '_token' => $this->generateCsrfToken($client, 'authorization'),
                'role' => $role->getId(),
                'organization' => $organization->getId(),
            ],
        ]);

        $this->assertSame(1, Factory\TeamAuthorizationFactory::count());

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $teamAuthorization = Factory\TeamAuthorizationFactory::last();
        $this->assertSame($team->getId(), $teamAuthorization->getTeam()->getId());
        $this->assertSame($role->getId(), $teamAuthorization->getRole()->getId());
        $this->assertSame($organization->getId(), $teamAuthorization->getOrganization()->getId());
    }

    public function testPostNewGrantsGlobalAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $role = Factory\RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $this->assertSame(0, Factory\TeamAuthorizationFactory::count());

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            'authorization' => [
                '_token' => $this->generateCsrfToken($client, 'authorization'),
                'role' => $role->getId(),
            ],
        ]);

        $this->assertSame(1, Factory\TeamAuthorizationFactory::count());

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $teamAuthorization = Factory\TeamAuthorizationFactory::last();
        $this->assertSame($team->getId(), $teamAuthorization->getTeam()->getId());
        $this->assertSame($role->getId(), $teamAuthorization->getRole()->getId());
        $this->assertNull($teamAuthorization->getOrganization());
    }

    public function testPostNewGrantsTeamAuthorizationsToTheAgents(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = Factory\UserFactory::createOne();
        $team = Factory\TeamFactory::createOne([
            'agents' => [$agent],
        ]);
        $role = Factory\RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            'authorization' => [
                '_token' => $this->generateCsrfToken($client, 'authorization'),
                'role' => $role->getId(),
            ],
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $agent->_refresh();
        $authorizations = $agent->getAuthorizations();
        $teamAuthorization = Factory\TeamAuthorizationFactory::last();
        $this->assertSame(1, count($authorizations));
        $this->assertSame($teamAuthorization->getId(), $authorizations[0]->getTeamAuthorization()->getId());
        $this->assertSame($role->getId(), $authorizations[0]->getRole()->getId());
        $this->assertNull($authorizations[0]->getOrganization());
    }

    public function testPostNewFailsIfRoleDoesNotExist(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            'authorization' => [
                '_token' => $this->generateCsrfToken($client, 'authorization'),
                'role' => 'not an id',
            ],
        ]);

        $this->assertSelectorTextContains('#authorization_role-error', 'The selected choice is invalid');
        $this->assertSame(0, Factory\TeamAuthorizationFactory::count());
    }

    public function testPostNewFailsIfNotAgentRole(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $role = Factory\RoleFactory::createOne([
            'type' => Foundry\faker()->randomElement(['user', 'admin', 'super']),
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            'authorization' => [
                '_token' => $this->generateCsrfToken($client, 'authorization'),
                'role' => $role->getId(),
            ],
        ]);

        $this->assertSelectorTextContains('#authorization_role-error', 'The selected choice is invalid');
        $this->assertSame(0, Factory\TeamAuthorizationFactory::count());
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $role = Factory\RoleFactory::createOne([
            'type' => 'agent',
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/authorizations/new", [
            'authorization' => [
                '_token' => 'not a token',
                'role' => $role->getId(),
            ],
        ]);

        $this->assertSelectorTextContains('#authorization-error', 'The security token is invalid');
        $this->assertSame(0, Factory\TeamAuthorizationFactory::count());
    }

    public function testPostDeleteDeletesTeamAuthorizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $teamAuthorization = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);
        $authorization = Factory\AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        Factory\TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        Factory\AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteUnsetsResponsibleTeamIfTeamHasNoLongerAccessToOrganization(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $organization = Factory\OrganizationFactory::createOne([
            'responsibleTeam' => $team,
        ]);
        $teamAuthorization = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $authorization = Factory\AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
            'organization' => $organization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        Factory\TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        Factory\AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
        $organization->_refresh();
        $this->assertNull($organization->getResponsibleTeam());
    }

    public function testPostDeleteDoesNotChangeResponsibleTeamIfTeamStillHasAccessToOrganization(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $organization = Factory\OrganizationFactory::createOne([
            'responsibleTeam' => $team,
        ]);
        $teamAuthorization = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $teamAuthorization2 = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $authorization = Factory\AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
            'organization' => $organization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        Factory\TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        Factory\AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
        $organization->_refresh();
        $this->assertNotNull($organization->getResponsibleTeam());
    }

    public function testPostDeleteDoesNotChangeResponsibleTeamIfTeamHasGlobalAccess(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $organization = Factory\OrganizationFactory::createOne([
            'responsibleTeam' => $team,
        ]);
        $teamAuthorization = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => $organization,
        ]);
        $teamAuthorization2 = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
            'organization' => null,
        ]);
        $authorization = Factory\AuthorizationFactory::createOne([
            'teamAuthorization' => $teamAuthorization,
            'organization' => $organization,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        Factory\TeamAuthorizationFactory::assert()->notExists(['id' => $teamAuthorization->getId()]);
        Factory\AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
        $organization->_refresh();
        $this->assertNotNull($organization->getResponsibleTeam());
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = Factory\TeamFactory::createOne();
        $teamAuthorization = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);

        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => 'not a token',
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        Factory\TeamAuthorizationFactory::assert()->exists(['id' => $teamAuthorization->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = Factory\TeamFactory::createOne();
        $teamAuthorization = Factory\TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/team-authorizations/{$teamAuthorization->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team authorization'),
        ]);
    }
}
