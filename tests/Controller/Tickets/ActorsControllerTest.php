<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\AuthorizationHelper;
use App\Tests\FactoriesHelper;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ActorsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/actors/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the actors');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/actors/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/actors/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantTeam($team->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'team' => null,
            'assignee' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'team' => $team->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertSame($team->getUid(), $ticket->getTeam()->getUid());
        $this->assertSame($assignee->getUid(), $ticket->getAssignee()->getUid());
    }

    public function testPostUpdateAcceptsEmptyAssignee(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'assignee' => '',
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfRequesterIsNotInOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'assignee' => $user->getId(),
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_actors_requester-error', 'The selected choice is invalid.');
        $ticket->_refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfAssigneeIsNotAgent(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantOrga($assignee->_real(), ['orga:create:tickets'], type: 'user');
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'assignee' => $user,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_actors_assignee-error', 'The selected choice is invalid');
        $ticket->_refresh();
        $this->assertSame($user->getUid(), $ticket->getRequester()->getUid());
        $this->assertSame($user->getUid(), $ticket->getAssignee()->getUid());
    }

    public function testPostUpdateFailsIfAssigneeIsNotInTeam(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantOrga($assignee->_real(), ['orga:create:tickets']);
        $this->grantTeam($team->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'team' => null,
            'assignee' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'team' => $team->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_actors_assignee-error', 'The selected choice is invalid');
        $ticket->_refresh();
        $this->assertSame($user->getUid(), $ticket->getRequester()->getUid());
        $this->assertNull($ticket->getTeam());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfTeamNotAuthorizedInOrga(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $initialTeam = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $newTeam = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantTeam($initialTeam->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'team' => $initialTeam,
            'assignee' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'team' => $newTeam->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_actors_team-error', 'The selected choice is invalid');
        $ticket->_refresh();
        $this->assertSame($user->getUid(), $ticket->getRequester()->getUid());
        $this->assertSame($initialTeam->getUid(), $ticket->getTeam()->getUid());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantOrga($assignee->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => 'not the token',
                'requester' => $requester->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_actors-error', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantOrga($assignee->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantOrga($assignee->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'requester' => null,
            'assignee' => null,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);
    }
}
