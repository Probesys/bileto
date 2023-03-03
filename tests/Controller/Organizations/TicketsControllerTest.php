<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity\Organization;
use App\Entity\Ticket;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
            'createdBy' => $user,
            'title' => 'My ticket',
            'organization' => $organization,
            'status' => Factory::faker()->randomElement(Ticket::OPEN_STATUSES),
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets");

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

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets");

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
        $this->grantOrga($user->object(), ['orga:see', 'orga:see:tickets:all'], $organization->object());
        $ticketAssigned = TicketFactory::createOne([
            'title' => 'Ticket assigned',
            'organization' => $organization,
            'assignee' => $user,
        ]);
        $ticketToAssign = TicketFactory::createOne([
            'title' => 'Ticket to assign',
            'organization' => $organization,
            'assignee' => null,
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets?assignee=none");

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
        $this->grantOrga($user->object(), ['orga:see'], $organization->object());
        $ticketOwned = TicketFactory::createOne([
            'title' => 'Ticket owned',
            'organization' => $organization,
            'assignee' => $user,
        ]);
        $ticketAssigned = TicketFactory::createOne([
            'title' => 'Ticket assigned to other',
            'organization' => $organization,
            'assignee' => UserFactory::createOne(),
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets?assignee={$user->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', 'Ticket owned');
        $this->assertSelectorTextNotContains('[data-test="ticket-item"]', 'Ticket assigned to other');
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
        $client->request('GET', "/organizations/{$organization->getUid()}/tickets");
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

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");

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
        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
    }

    public function testPostCreateCreatesATicketAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee
        ) = UserFactory::createMany(3);
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
        $this->assertSame(0, MessageFactory::count());

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            'title' => $title,
            'requesterId' => $requester->getId(),
            'assigneeId' => $assignee->getId(),
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
        $this->assertSame($assignee->getId(), $ticket->getAssignee()->getId());
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => $user->getId(),
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

    public function testPostCreateCanMarkATicketAsResolved(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => $requester->getId(),
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

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
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
        $this->assertSame('request', $ticket->getType());
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => $user->getId(),
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => $user->getId(),
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => $user->getId(),
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => -1,
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#requester-error', 'Select a user from the list');
    }

    public function testPostCreateFailsIfAssigneeIsInvalid(): void
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => $user->getId(),
            'assigneeId' => -1,
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#assignee-error', 'Select a user from the list');
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

        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => 'not the token',
            'title' => $title,
            'requesterId' => $user->getId(),
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
        $client->request('POST', "/organizations/{$organization->getUid()}/tickets/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization ticket'),
            'title' => $title,
            'requesterId' => $user->getId(),
            'message' => $messageContent,
        ]);
    }
}
