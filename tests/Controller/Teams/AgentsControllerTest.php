<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Teams;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TeamAuthorizationFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AgentsControllerTest extends WebTestCase
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
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/agents/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New agent');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/agents/new");
    }

    public function testPostCreateAddsTheAgentAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne();

        $this->assertFalse($team->hasAgent($agent->_real()));

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agent->getEmail(),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $this->assertTrue($team->hasAgent($agent->_real()));
    }

    public function testPostCreateCreatesTheAgentIfEmailDoesNotExist(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $agentEmail = 'alix@example.com';

        $this->assertSame(1, UserFactory::count());

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agentEmail,
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $this->assertSame(2, UserFactory::count());
        $agent = UserFactory::last();
        $this->assertSame($agentEmail, $agent->getEmail());
        $this->assertTrue($team->hasAgent($agent->_real()));
    }

    public function testPostCreateGrantsTeamAuthorizationsToTheAgent(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne();
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agent->getEmail(),
        ]);

        $agent->_refresh();
        $authorizations = $agent->getAuthorizations();
        $this->assertSame(1, count($authorizations));
        $this->assertSame($teamAuthorization->getId(), $authorizations[0]->getTeamAuthorization()->getId());
    }

    public function testPostCreateFailsIfEmailIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $agentEmail = '';

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agentEmail,
        ]);

        $this->assertSelectorTextContains('#email-error', 'Enter an email address');
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostCreateFailsIfEmailIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $agentEmail = 'not an email';

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agentEmail,
        ]);

        $this->assertSelectorTextContains('#email-error', 'Enter a valid email address');
        $this->assertSame(1, UserFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne();

        $this->assertFalse($team->hasAgent($agent->_real()));

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => 'not a token',
            'agentEmail' => $agent->getEmail(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $this->assertFalse($team->hasAgent($agent->_real()));
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agent->getEmail(),
        ]);
    }

    public function testPostDeleteRemovesTheAgentFromTheTeam(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);

        $this->assertTrue($team->hasAgent($agent->_real()));

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'remove team agent'),
            'agentUid' => $agent->getUid(),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $this->assertFalse($team->hasAgent($agent->_real()));
    }

    public function testPostDeleteUngrantsTeamAuthorizationsFromTheAgent(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);
        $teamAuthorization = TeamAuthorizationFactory::createOne([
            'team' => $team,
        ]);
        $authorization = AuthorizationFactory::createOne([
            'holder' => $agent,
            'teamAuthorization' => $teamAuthorization,
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'remove team agent'),
            'agentUid' => $agent->getUid(),
        ]);

        TeamAuthorizationFactory::assert()->exists(['id' => $teamAuthorization->getId()]);
        AuthorizationFactory::assert()->notExists(['id' => $authorization->getId()]);
    }

    public function testPostDeleteDoesNotFailIfAgentUidDoesNotExist(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'remove team agent'),
            'agentUid' => 'not a uid',
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);

        $this->assertTrue($team->hasAgent($agent->_real()));

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/deletion", [
            '_csrf_token' => 'not a token',
            'agentUid' => $agent->getUid(),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        $this->assertTrue($team->hasAgent($agent->_real()));
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/agents/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'remove team agent'),
            'agentUid' => $agent->getUid(),
        ]);
    }
}
