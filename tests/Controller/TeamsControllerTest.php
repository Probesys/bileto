<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Team;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TeamAuthorizationFactory;
use App\Tests\FactoriesHelper;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TeamsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsTeamsSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team1 = TeamFactory::createOne([
            'name' => 'foo',
        ]);
        $team2 = TeamFactory::createOne([
            'name' => 'bar',
        ]);

        $client->request(Request::METHOD_GET, '/teams');

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
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/teams');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);

        $client->request(Request::METHOD_GET, '/teams/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New team');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/teams/new');
    }

    public function testPostNewCreatesTheTeamAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $name = 'My team';

        $this->assertSame(0, TeamFactory::count());

        $crawler = $client->request(Request::METHOD_POST, '/teams/new', [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $name,
            ],
        ]);

        $this->assertSame(1, TeamFactory::count());
        $team = TeamFactory::last();
        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $this->assertSame($name, $team->getName());
        $this->assertSame(20, strlen($team->getUid()));
    }

    public function testPostNewFailsIfNameIsAlreadyUsed(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $name = 'My team';
        TeamFactory::createOne([
            'name' => $name,
        ]);

        $crawler = $client->request(Request::METHOD_POST, '/teams/new', [
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

    public function testPostNewFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $name = '';

        $crawler = $client->request(Request::METHOD_POST, '/teams/new', [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains('#team_name-error', 'Enter a name');
        $this->assertSame(0, TeamFactory::count());
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $name = 'My team';

        $crawler = $client->request(Request::METHOD_POST, '/teams/new', [
            'team' => [
                '_token' => 'not a token',
                'name' => $name,
            ],
        ]);

        $this->assertSelectorTextContains('#team-error', 'The security token is invalid');
        $this->assertSame(0, TeamFactory::count());
    }

    public function testGetShowRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne([
            'name' => 'foo',
        ]);

        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $team->getName());
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne([
            'name' => 'foo',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}");
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne();

        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit a team');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/teams/{$team->getUid()}/edit");
    }

    public function testPostEditSavesTheTeamAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $initialName = 'team';
        $newName = 'My team';
        $team = TeamFactory::createOne([
            'name' => $initialName,
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/edit", [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $newName,
            ],
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}", 302);
        $team->_refresh();
        $this->assertSame($newName, $team->getName());
    }

    public function testPostEditFailsIfNameIsAlreadyUsed(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $initialName = 'team';
        $newName = 'My team';
        $team = TeamFactory::createOne([
            'name' => $initialName,
        ]);
        TeamFactory::createOne([
            'name' => $newName,
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/edit", [
            'team' => [
                '_token' => $this->generateCsrfToken($client, 'team'),
                'name' => $newName,
            ],
        ]);

        $this->assertSelectorTextContains(
            '#team_name-error',
            'Enter a different name, a team already has this name',
        );
        $this->clearEntityManager();
        $team->_refresh();
        $this->assertSame($initialName, $team->getName());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $initialName = 'team';
        $newName = 'My team';
        $team = TeamFactory::createOne([
            'name' => $initialName,
        ]);

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/edit", [
            'team' => [
                '_token' => 'not a token',
                'name' => $newName,
            ],
        ]);

        $this->assertSelectorTextContains('#team-error', 'The security token is invalid');
        $this->clearEntityManager();
        $team->_refresh();
        $this->assertSame($initialName, $team->getName());
    }

    public function testPostDeleteRemovesTheTeamAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $agent = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);
        $this->grantTeam($team->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
            'team' => $team,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team'),
        ]);

        $this->assertResponseRedirects('/teams', 302);
        TeamFactory::assert()->notExists(['id' => $team->getId()]);
        TeamAuthorizationFactory::assert()->count(0);
        AuthorizationFactory::assert()->count(1);
        $ticket->_refresh();
        $this->assertNull($ticket->getTeam());
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $agent = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:agents']);
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);
        $this->grantTeam($team->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
            'team' => $team,
        ]);

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/deletion", [
            '_csrf_token' => 'not a token',
        ]);

        $this->assertResponseRedirects("/teams/{$team->getUid()}/edit", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        TeamFactory::assert()->exists(['id' => $team->getId()]);
        TeamAuthorizationFactory::assert()->count(1);
        AuthorizationFactory::assert()->count(2);
        $ticket->_refresh();
        $this->assertNotNull($ticket->getTeam());
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $agent = UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne([
            'agents' => [$agent],
        ]);
        $this->grantTeam($team->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
            'team' => $team,
        ]);

        $this->clearEntityManager();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/teams/{$team->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete team'),
        ]);
    }
}
