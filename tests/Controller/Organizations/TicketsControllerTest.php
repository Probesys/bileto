<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity\Organization;
use App\Factory\MessageFactory;
use App\Factory\OrganizationFactory;
use App\Factory\TicketFactory;
use App\Factory\UserFactory;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Tickets');
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
            'status' => 'assigned',
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
        $this->assertSame('assigned', $ticket->getStatus());
        $this->assertSame('medium', $ticket->getUrgency());
        $this->assertSame('medium', $ticket->getImpact());
        $this->assertSame('medium', $ticket->getPriority());
        $this->assertSame($requester->getId(), $ticket->getRequester()->getId());
        $this->assertSame($assignee->getId(), $ticket->getAssignee()->getId());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
        $message = MessageFactory::first();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertEquals($now, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isPrivate());
        $this->assertFalse($message->isSolution());
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
