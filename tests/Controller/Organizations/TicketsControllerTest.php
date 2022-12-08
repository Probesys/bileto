<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity\Organization;
use App\Entity\Ticket;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);
        $ticket = TicketFactory::createOne([
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

    public function testGetIndexRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New ticket');
    }

    public function testGetNewRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        $organization = OrganizationFactory::createOne();

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");

        $this->assertResponseRedirects('http://localhost/login', 302);
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
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            'title' => $title,
            'requesterId' => $requester->getId(),
            'assigneeId' => $assignee->getId(),
            'status' => 'planned',
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
        $this->assertSame('request', $ticket->getType());
        $this->assertSame('planned', $ticket->getStatus());
        $this->assertSame('medium', $ticket->getUrgency());
        $this->assertSame('medium', $ticket->getImpact());
        $this->assertSame('medium', $ticket->getPriority());
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
        $title = 'My ticket';
        $messageContent = 'My message <style>body { background-color: pink; }</style>';

        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            'title' => $title,
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

    public function testPostCreateFailsIfTitleIsEmpty(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $title = '';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            'title' => $title,
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#title-error', 'The title is required.');
    }

    public function testPostCreateFailsIfTitleIsTooLong(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $title = str_repeat('a', 256);
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            'title' => $title,
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#title-error', 'The title must be 255 characters maximum.');
    }

    public function testPostCreateFailsIfMessageIsEmpty(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $title = 'My ticket';
        $messageContent = '';

        $this->assertSame(0, TicketFactory::count());

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            'title' => $title,
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#message-error', 'The message is required.');
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $title = 'My ticket';
        $messageContent = 'My message';

        $this->assertSame(0, TicketFactory::count());

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets/new");
        $crawler = $client->submitForm('form-create-ticket-submit', [
            '_csrf_token' => 'not the token',
            'title' => $title,
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
    }
}
