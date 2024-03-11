<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Teams;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request('GET', "/teams/{$team->getUid()}/agents/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New agent');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $team = TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/teams/{$team->getUid()}/agents/new");
    }

    public function testPostCreateAddsTheAgentAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne();

        $this->assertFalse($team->hasAgent($agent->object()));

        $client->request('POST', "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agent->getEmail(),
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $this->assertTrue($team->hasAgent($agent->object()));
    }

    public function testPostCreateCreatesTheAgentIfEmailDoesNotExist(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $agentEmail = 'alix@example.com';

        $this->assertSame(1, UserFactory::count());

        $client->request('POST', "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agentEmail,
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $this->assertSame(2, UserFactory::count());
        $agent = UserFactory::last();
        $this->assertSame($agentEmail, $agent->getEmail());
        $this->assertTrue($team->hasAgent($agent->object()));
    }

    public function testPostCreateFailsIfEmailIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $agentEmail = '';

        $client->request('POST', "/teams/{$team->getUid()}/agents/new", [
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
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();
        $agentEmail = 'not an email';

        $client->request('POST', "/teams/{$team->getUid()}/agents/new", [
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
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne();

        $this->assertFalse($team->hasAgent($agent->object()));

        $client->request('POST', "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => 'not a token',
            'agentEmail' => $agent->getEmail(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $this->assertFalse($team->hasAgent($agent->object()));
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $agent = UserFactory::createOne();
        $team = TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request('POST', "/teams/{$team->getUid()}/agents/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'add team agent'),
            'agentEmail' => $agent->getEmail(),
        ]);
    }
}
