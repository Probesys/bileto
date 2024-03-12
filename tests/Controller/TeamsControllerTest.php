<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Team;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TeamsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsTeamsSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team1 = TeamFactory::createOne([
            'name' => 'foo',
        ]);
        $team2 = TeamFactory::createOne([
            'name' => 'bar',
        ]);

        $client->request('GET', '/teams');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Teams & Agents');
        $this->assertSelectorTextContains('[data-test="team-item"]:nth-child(1)', 'bar');
        $this->assertSelectorTextContains('[data-test="team-item"]:nth-child(2)', 'foo');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/teams');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);

        $client->request('GET', '/teams/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New team');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/teams/new');
    }

    public function testPostCreateCreatesTheTeamAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $name = 'My team';

        $this->assertSame(0, TeamFactory::count());

        $crawler = $client->request('POST', '/teams/new', [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $name,
            ],
        ]);

        $this->assertResponseRedirects('/teams', 302);
        $this->assertSame(1, TeamFactory::count());
        $team = TeamFactory::last();
        $this->assertSame($name, $team->getName());
        $this->assertSame(20, strlen($team->getUid()));
    }

    public function testPostCreateFailsIfNameIsAlreadyUsed(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $name = 'My team';
        TeamFactory::createOne([
            'name' => $name,
        ]);

        $crawler = $client->request('POST', '/teams/new', [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains(
            '#team_name-error',
            'Enter a different name, a team already has this name',
        );
        $this->assertSame(1, TeamFactory::count());
    }

    public function testPostCreateFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $name = '';

        $crawler = $client->request('POST', '/teams/new', [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains('#team_name-error', 'Enter a name');
        $this->assertSame(0, TeamFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $name = 'My team';

        $crawler = $client->request('POST', '/teams/new', [
            'team' => [
                '_token' => 'not a token',
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains('#team-error', 'The security token is invalid');
        $this->assertSame(0, TeamFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My team';

        $client->catchExceptions(false);
        $crawler = $client->request('POST', '/teams/new', [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $name,
            ],
        ]);
    }

    public function testGetShowRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:agents']);
        $team = TeamFactory::createOne([
            'name' => 'foo',
        ]);

        $client->request('GET', "/teams/{$team->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $team->getName());
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $team = TeamFactory::createOne([
            'name' => 'foo',
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/teams/{$team->getUid()}");
    }
}
