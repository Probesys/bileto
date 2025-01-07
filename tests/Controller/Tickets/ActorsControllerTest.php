<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
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
            'status' => 'in_progress',
            'createdBy' => $user,
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/actors/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the actors');
    }

    public function testGetEditFailsIfTicketIsClosed(): void
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
            'status' => 'closed',
            'createdBy' => $user,
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/actors/edit");
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
            'status' => 'in_progress',
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
            'status' => 'in_progress',
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/actors/edit");
    }

    public function testPostEditSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
            $observer1,
            $observer2,
        ) = UserFactory::createMany(5);
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->_real(), ['orga:create:tickets']);
        $this->grantOrga($observer1->_real(), ['orga:see']);
        $this->grantOrga($observer2->_real(), ['orga:see']);
        $this->grantTeam($team->_real(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'requester' => null,
            'team' => null,
            'assignee' => null,
            'observers' => [],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $requester->getId(),
                'team' => $team->getId(),
                'assignee' => $assignee->getId(),
                'observers' => [$observer1->getId(), $observer2->getId()],
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertSame($team->getUid(), $ticket->getTeam()->getUid());
        $this->assertSame($assignee->getUid(), $ticket->getAssignee()->getUid());
        $observers = $ticket->getObservers();
        $this->assertSame(2, count($observers));
        $this->assertEqualsCanonicalizing(
            [$observer1->getId(), $observer2->getId()],
            [$observers[0]->getId(), $observers[1]->getId()],
        );
    }

    public function testPostEditAcceptsEmptyAssignee(): void
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
            'status' => 'in_progress',
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

    public function testPostEditFailsIfRequesterIsNotInOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
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

    public function testPostEditFailsIfObserverIsNotInOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $observer,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
            'observers' => [],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/actors/edit", [
            'ticket_actors' => [
                '_token' => $this->generateCsrfToken($client, 'ticket actors'),
                'requester' => $user->getId(),
                'assignee' => $user->getId(),
                'observers' => [$observer->getId()],
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_actors_observers-error', 'The selected choice is invalid.');
        $ticket->_refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
        $this->assertSame(0, count($ticket->getObservers()));
    }

    public function testPostEditFailsIfAssigneeIsNotAgent(): void
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
            'status' => 'in_progress',
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

    public function testPostEditFailsIfAssigneeIsNotInTeam(): void
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
            'status' => 'in_progress',
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

    public function testPostEditFailsIfTeamNotAuthorizedInOrga(): void
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
            'status' => 'in_progress',
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

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
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
            'status' => 'in_progress',
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
}
