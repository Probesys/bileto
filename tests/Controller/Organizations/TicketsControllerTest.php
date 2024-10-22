<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity;
use App\Tests;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\SessionHelper;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see'], $organization->_real());
        $ticket = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'title' => 'My ticket',
            'organization' => $organization,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', 'My ticket');
    }

    public function testGetIndexDoesNotRenderFinishedTickets(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see'], $organization->_real());
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'organization' => $organization,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::FINISHED_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-test="ticket-item"]');
    }

    public function testGetIndexCanFilterTicketsToAssign(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all']);
        $ticketAssigned = Factory\TicketFactory::createOne([
            'title' => 'Ticket assigned',
            'organization' => $organization,
            'assignee' => $user,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES),
        ]);
        $ticketToAssign = Factory\TicketFactory::createOne([
            'title' => 'Ticket to assign',
            'organization' => $organization,
            'assignee' => null,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets?view=unassigned");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', 'Ticket to assign');
        $this->assertSelectorTextNotContains('[data-test="ticket-item"]', 'Ticket assigned');
    }

    public function testGetIndexCanFilterOwnedTickets(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all']);
        Factory\TicketFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'minute'),
            'title' => 'Ticket assigned to user',
            'organization' => $organization,
            'assignee' => $user,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES),
        ]);
        Factory\TicketFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'minutes'),
            'title' => 'Ticket requested by user',
            'organization' => $organization,
            'requester' => $user,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES),
        ]);
        Factory\TicketFactory::createOne([
            'title' => 'Other ticket',
            'organization' => $organization,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES),
        ]);

        $client->request(
            Request::METHOD_GET,
            "/organizations/{$organization->getUid()}/tickets?view=owned&sort=title-asc"
        );

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]:nth-child(1)', 'Ticket assigned to user');
        $this->assertSelectorTextContains('[data-test="ticket-item"]:nth-child(2)', 'Ticket requested by user');
        $this->assertSelectorTextNotContains('[data-test="ticket-item"]', 'Other ticket');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets");
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->_real());

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New ticket');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets/new");
    }

    public function testPostNewCreatesATicketAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Utils\Time::freeze($now);
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->_real());
        $this->grantOrga($requester->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'requester' => $requester->getId(),
                'type' => 'incident',
                'urgency' => 'high',
                'impact' => 'high',
                'priority' => 'high',
            ],
        ]);

        Utils\Time::unfreeze();
        $this->assertSame(1, Factory\TicketFactory::count());
        $this->assertSame(1, Factory\MessageFactory::count());

        $ticket = Factory\TicketFactory::last();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame($title, $ticket->getTitle());
        $this->assertSame(20, strlen($ticket->getUid()));
        $this->assertEquals($now, $ticket->getCreatedAt());
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame('incident', $ticket->getType());
        $this->assertSame('new', $ticket->getStatus());
        $this->assertSame('high', $ticket->getUrgency());
        $this->assertSame('high', $ticket->getImpact());
        $this->assertSame('high', $ticket->getPriority());
        $this->assertNull($ticket->getSolution());
        $this->assertSame($requester->getId(), $ticket->getRequester()->getId());
        $this->assertNull($ticket->getAssignee());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
        $message = Factory\MessageFactory::last();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertEquals($now, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('webapp', $message->getVia());
        $this->assertEmailCount(2);
        $email = $this->getMailerMessage();
        $this->assertEmailHtmlBodyContains($email, 'We have received your support request.');
    }

    public function testPostNewCanCreateATicketWithMinimalPermissions(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame($title, $ticket->getTitle());
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame('incident', $ticket->getType());
        $this->assertSame('new', $ticket->getStatus());
        $this->assertSame('medium', $ticket->getUrgency());
        $this->assertSame('medium', $ticket->getImpact());
        $this->assertSame('medium', $ticket->getPriority());
        $this->assertSame($user->getId(), $ticket->getRequester()->getId());
        $this->assertNull($ticket->getAssignee());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
        $message = Factory\MessageFactory::last();
        $this->assertNotNull($message);
        $this->assertSame($messageContent, $message->getContent());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('webapp', $message->getVia());
    }

    public function testPostNewSanitizesTheMessageContent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message <style>body { background-color: pink; }</style>';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = Factory\MessageFactory::last();
        $this->assertNotNull($message);
        $this->assertSame('My message', $message->getContent());
    }

    public function testPostNewAttachesAContractIfItExists(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';
        $ongoingContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $ticketContract = $ticket->getOngoingContract();
        $this->assertNotNull($ticketContract);
        $this->assertSame($ongoingContract->getId(), $ticketContract->getId());
    }

    public function testPostNewAttachesDocumentsToMessage(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';
        list($messageDocument1, $messageDocument2) = Factory\MessageDocumentFactory::createMany(2, [
            'createdBy' => $user->_real(),
            'message' => null,
        ]);

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $message = Factory\MessageFactory::last();
        $messageDocument1->_refresh();
        $messageDocument2->_refresh();
        $this->assertNotNull($message);
        $this->assertSame($message->getUid(), $messageDocument1->getMessage()->getUid());
        $this->assertSame($message->getUid(), $messageDocument2->getMessage()->getUid());
    }

    public function testPostNewAssignAResponsibleTeamByDefault(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team = Factory\TeamFactory::createOne([
            'isResponsible' => true,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $this->grantTeam($team->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('new', $ticket->getStatus());
        $this->assertSame($team->getId(), $ticket->getTeam()->getId());
    }

    public function testPostNewCanAssignATeamAndAnAgent(): void
    {
        $client = static::createClient();
        list(
            $user,
            $assignee
        ) = Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team = Factory\TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:actors',
        ], $organization->_real());
        $this->grantTeam($team->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'requester' => $user->getId(),
                'team' => $team->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('in_progress', $ticket->getStatus());
        $this->assertSame($team->getId(), $ticket->getTeam()->getId());
        $this->assertSame($assignee->getId(), $ticket->getAssignee()->getId());
    }

    public function testPostNewCanSetOrganizationObservers(): void
    {
        $client = static::createClient();
        list(
            $user,
            $observer,
        ) = Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne([
            'observers' => [$observer],
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $this->grantOrga($observer->_real(), ['orga:see'], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticketObservers = $ticket->getObservers();
        $this->assertSame(1, count($ticketObservers));
        $this->assertSame($observer->getUid(), $ticketObservers[0]->getUid());
    }

    public function testPostNewCanSetLabels(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:labels',
        ], $organization->_real());
        list(
            $label1,
            $label2,
        ) = Factory\LabelFactory::createMany(2);
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'labels' => [$label2->getId()],
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticketLabels = $ticket->getLabels();
        $this->assertSame(1, count($ticketLabels));
        $this->assertSame($label2->getUid(), $ticketLabels[0]->getUid());
    }

    public function testPostNewCanMarkATicketAsResolved(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'isResolved' => true,
            ],
        ]);

        $ticket = Factory\TicketFactory::last();
        $this->assertNotNull($ticket);
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('resolved', $ticket->getStatus());
    }

    public function testPostNewFailsIfAssigneeIsNotAgent(): void
    {
        $client = static::createClient();
        list(
            $user,
            $assignee
        ) = Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:actors',
        ], $organization->_real());
        $this->grantOrga($assignee->_real(), [
            'orga:create:tickets',
        ], $organization->_real(), 'user');
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'requester' => $user->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_assignee-error', 'The selected choice is invalid');
    }

    public function testPostNewFailsIfAssignedTeamIsNotAuthorizedInOrga(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team1 = Factory\TeamFactory::createOne();
        $team2 = Factory\TeamFactory::createOne();
        $this->grantTeam($team1->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:actors',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'team' => $team2->getId(),
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_team-error', 'The selected choice is invalid');
    }

    public function testPostNewFailsIfAssignedAgentIsNotInTeam(): void
    {
        $client = static::createClient();
        list(
            $user,
            $assignee
        ) = Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team = Factory\TeamFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:actors',
        ], $organization->_real());
        $this->grantOrga($assignee->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $this->grantTeam($team->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'team' => $team->getId(),
                'assignee' => $assignee->getId(),
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_assignee-error', 'The selected choice is invalid');
    }

    public function testPostNewFailsIfRequesterIsNotInOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:actors',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'requester' => $requester->getId(),
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_requester-error', 'The selected choice is invalid');
    }

    public function testPostNewFailsIfRequesterIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
            'orga:update:tickets:actors',
        ], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
                'requester' => 'not an id',
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_requester-error', 'The selected choice is invalid');
    }

    public function testPostNewFailsIfTitleIsEmpty(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = '';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_title-error', 'Enter a title');
    }

    public function testPostNewFailsIfTitleIsTooLong(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = str_repeat('a', 256);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_title-error', 'Enter a title of less than 255 characters');
    }

    public function testPostNewFailsIfMessageIsEmpty(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = 'My ticket';
        $messageContent = '';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'ticket'),
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket_content-error', 'Enter a message');
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            'ticket' => [
                '_token' => 'not the token',
                'title' => $title,
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(0, Factory\TicketFactory::count());
        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#ticket-error', 'The security token is invalid');
    }
}
