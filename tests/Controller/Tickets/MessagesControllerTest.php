<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Entity\Ticket;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessagesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testPostCreateCreatesAMessageAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('GET', "/tickets/{$ticket->getUid()}");
        $crawler = $client->submitForm('form-create-message-submit', [
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
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
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message <style>body { background-color: pink; }</style>';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'status' => 'in_progress',
        ]);

        $this->assertSame(1, MessageFactory::count());

        $message = MessageFactory::first();
        $this->assertSame('My message', $message->getContent());
    }

    public function testPostCreateChangesTheTicketStatus(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = 'in_progress';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'status' => 'pending',
        ]);

        Time::unfreeze();
        $ticket->refresh();
        $this->assertSame('pending', $ticket->getStatus());
    }

    public function testPostCreateForcesStatusToResolvedIfIsSolutionIsTrue(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'status' => 'in_progress',
            'isSolution' => true,
        ]);

        Time::unfreeze();
        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $ticket->refresh();
        $this->assertSame($message->getId(), $ticket->getSolution()->getId());
        $this->assertSame('resolved', $ticket->getStatus());
    }

    public function testPostCreateForcesIsSolutionToFalseIfIsConfidentialIsTrue(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'status' => 'in_progress',
            'isSolution' => true,
            'isConfidential' => true,
        ]);

        Time::unfreeze();
        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $this->assertTrue($message->isConfidential());
        $ticket->refresh();
        $this->assertNull($ticket->getSolution());
    }

    public function testPostCreateDoesNotChangeTheTicketStatusAndForcesIsSolutionToFalseIfStatusIsFinished(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::FINISHED_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'status' => 'in_progress',
            'isSolution' => true,
        ]);

        Time::unfreeze();
        $ticket->refresh();
        $this->assertSame($initialStatus, $ticket->getStatus());
        $this->assertNull($ticket->getSolution());
    }

    public function testPostCreateFailsIfMessageIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = '';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'status' => 'in_progress',
        ]);

        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#message-error', 'The message is required.');
    }

    public function testPostCreateFailsIfStatusIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'status' => 'invalid',
        ]);

        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#status-error', 'The status "invalid" is not a valid status.');
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => 'not the token',
            'message' => $messageContent,
            'status' => 'in_progress',
        ]);

        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
    }
}
