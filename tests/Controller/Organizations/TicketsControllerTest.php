<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity\Organization;
use App\Entity\Ticket;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\MessageDocumentFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);
        $this->grantOrga($user->object(), ['orga:see'], $organization->object());
        $ticket = TicketFactory::createOne([
            'assignee' => $user,
            'title' => 'My ticket',
            'organization' => $organization,
            'status' => Factory::faker()->randomElement(Ticket::OPEN_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', 'My ticket');
    }

    public function testGetIndexDoesNotRenderFinishedTickets(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);
        $this->grantOrga($user->object(), ['orga:see'], $organization->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'organization' => $organization,
            'status' => Factory::faker()->randomElement(Ticket::FINISHED_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-test="ticket-item"]');
    }

    public function testGetIndexCanFilterTicketsToAssign(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);
        $this->grantOrga($user->object(), ['orga:see', 'orga:see:tickets:all']);
        $ticketAssigned = TicketFactory::createOne([
            'title' => 'Ticket assigned',
            'organization' => $organization,
            'assignee' => $user,
            'status' => Factory::faker()->randomElement(Ticket::OPEN_STATUSES),
        ]);
        $ticketToAssign = TicketFactory::createOne([
            'title' => 'Ticket to assign',
            'organization' => $organization,
            'assignee' => null,
            'status' => Factory::faker()->randomElement(Ticket::OPEN_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets?view=unassigned");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', 'Ticket to assign');
        $this->assertSelectorTextNotContains('[data-test="ticket-item"]', 'Ticket assigned');
    }

    public function testGetIndexCanFilterOwnedTickets(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);
        $this->grantOrga($user->object(), ['orga:see', 'orga:see:tickets:all']);
        TicketFactory::createOne([
            'createdAt' => Time::ago(1, 'minute'),
            'title' => 'Ticket assigned to user',
            'organization' => $organization,
            'assignee' => $user,
            'status' => Factory::faker()->randomElement(Ticket::OPEN_STATUSES),
        ]);
        TicketFactory::createOne([
            'createdAt' => Time::ago(2, 'minutes'),
            'title' => 'Ticket requested by user',
            'organization' => $organization,
            'requester' => $user,
            'status' => Factory::faker()->randomElement(Ticket::OPEN_STATUSES),
        ]);
        TicketFactory::createOne([
            'title' => 'Other ticket',
            'organization' => $organization,
            'status' => Factory::faker()->randomElement(Ticket::OPEN_STATUSES),
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
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets");
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New ticket');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets/new");
    }

    public function testPostCreateCreatesATicketAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $this->grantOrga($requester->object(), [
            'orga:create:tickets',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $requester->getUid(),
            'assigneeUid' => null,
            'type' => 'incident',
            'urgency' => 'high',
            'impact' => 'high',
            'priority' => 'high',
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
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
        $message = MessageFactory::first();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertEquals($now, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('webapp', $message->getVia());
    }

    public function testPostCreateSanitizesTheMessageContent(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message <style>body { background-color: pink; }</style>';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $this->assertSame('My message', $message->getContent());
    }

    public function testPostCreateAttachesAContractIfItExists(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), ['orga:create:tickets'], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';
        $ongoingContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Time::ago(1, 'week'),
            'endAt' => Time::fromNow(1, 'week'),
        ]);

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
        ]);

        $ticket = TicketFactory::first();
        $this->assertNotNull($ticket);
        $ticketContract = $ticket->getOngoingContract();
        $this->assertNotNull($ticketContract);
        $this->assertSame($ongoingContract->getId(), $ticketContract->getId());
    }

    public function testPostCreateAttachesDocumentsToMessage(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), ['orga:create:tickets'], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';
        list($messageDocument1, $messageDocument2) = MessageDocumentFactory::createMany(2, [
            'createdBy' => $user->object(),
            'message' => null,
        ]);

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
            'messageDocumentUids' => [
                $messageDocument1->getUid(),
                $messageDocument2->getUid(),
            ],
        ]);

        $message = MessageFactory::first();
        $messageDocument1->refresh();
        $messageDocument2->refresh();
        $this->assertNotNull($message);
        $this->assertSame($message->getUid(), $messageDocument1->getMessage()->getUid());
        $this->assertSame($message->getUid(), $messageDocument2->getMessage()->getUid());
    }

    public function testPostCreateCanAssignATeamAndAnAgent(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        $client = static::createClient();
        list(
            $user,
            $assignee
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $this->grantTeam($team->object(), [
            'orga:create:tickets',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'teamUid' => $team->getUid(),
            'assigneeUid' => $assignee->getUid(),
            'message' => $messageContent,
        ]);

        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('in_progress', $ticket->getStatus());
        $this->assertSame($team->getId(), $ticket->getTeam()->getId());
        $this->assertSame($assignee->getId(), $ticket->getAssignee()->getId());
    }

    public function testPostCreateCanMarkATicketAsResolved(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
            'isResolved' => true,
        ]);

        $this->assertSame(1, TicketFactory::count());

        $ticket = TicketFactory::first();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('resolved', $ticket->getStatus());
    }

    public function testPostCreateCanCreateATicketWithMinimalPermissions(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), ['orga:create:tickets'], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            'title' => $title,
            'message' => $messageContent,
        ]);

        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
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
        $message = MessageFactory::first();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('webapp', $message->getVia());
    }

    public function testPostCreateDoesNotAssignIfUserIsNotAgent(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        $client = static::createClient();
        list(
            $user,
            $assignee
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $this->grantOrga($assignee->object(), [
            'orga:create:tickets',
        ], $organization->object(), 'user');
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'assigneeUid' => $assignee->getUid(),
            'type' => 'incident',
            'urgency' => 'high',
            'impact' => 'high',
            'priority' => 'high',
            'message' => $messageContent,
        ]);

        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('new', $ticket->getStatus());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostCreateDoesNotAssignIfTeamNotAuthorizedInOrga(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        $client = static::createClient();
        list(
            $user,
            $assignee
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $team = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'teamUid' => $team->getUid(),
            'assigneeUid' => $assignee->getUid(),
            'message' => $messageContent,
        ]);

        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('new', $ticket->getStatus());
        $this->assertNull($ticket->getTeam());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostCreateDoesNotAssignIfAgentIsNotInTeam(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        $client = static::createClient();
        list(
            $user,
            $assignee
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $team = TeamFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $this->grantOrga($assignee->object(), [
            'orga:create:tickets',
        ], $organization->object());
        $this->grantTeam($team->object(), [
            'orga:create:tickets',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'teamUid' => $team->getUid(),
            'assigneeUid' => $assignee->getUid(),
            'message' => $messageContent,
        ]);

        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertSame('new', $ticket->getStatus());
        $this->assertSame($team->getId(), $ticket->getTeam()->getId());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostCreateFailsIfRequesterIsNotInOrganization(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $requester->getUid(),
            'message' => $messageContent,
        ]);

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#requester-error', 'Select a user from the list');
    }

    public function testPostCreateFailsIfTitleIsEmpty(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = '';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#title-error', 'Enter a title');
    }

    public function testPostCreateFailsIfTitleIsTooLong(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = str_repeat('a', 256);
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#title-error', 'Enter a title of less than 255 characters');
    }

    public function testPostCreateFailsIfMessageIsEmpty(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = '';

        $this->assertSame(0, TicketFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#message-error', 'Enter a message');
    }

    public function testPostCreateFailsIfRequesterIsInvalid(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => 'not an uid',
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#requester-error', 'Select a user from the list');
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:update:tickets:status',
            'orga:update:tickets:type',
            'orga:update:tickets:actors',
            'orga:update:tickets:priority',
        ], $organization->object());
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => 'not the token',
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $title = 'My ticket';
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterUid' => $user->getUid(),
            'message' => $messageContent,
        ]);
    }
}
