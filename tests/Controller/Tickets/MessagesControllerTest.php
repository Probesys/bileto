<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Entity\Ticket;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils\Time;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MessagesControllerTest extends WebTestCase
{
    use AuthorizationHelper;
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
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'pending',
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
        $ticket->refresh();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertEquals($now, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('webapp', $message->getVia());
        $this->assertSame('pending', $ticket->getStatus());
    }

    public function testPostCreateSanitizesTheMessageContent(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message <style>body { background-color: pink; }</style>';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);

        $this->assertSame(1, MessageFactory::count());

        $message = MessageFactory::first();
        $this->assertSame('My message', $message->getContent());
    }

    public function testPostCreateCanChangeTheTicketStatusIfPermissionsAreGranted(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:update:tickets:status',
        ]);
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
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:update:tickets:status',
        ]);
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
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:messages:confidential',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
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
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:update:tickets:status',
        ]);
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

    public function testPostCreateDoesNotChangeTheTicketStatusIfPermissionsAreNotGranted(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Time::freeze($now);
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
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
        $this->assertSame($initialStatus, $ticket->getStatus());
    }

    public function testPostCreateFailsIfMessageIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = '';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);

        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#message-error', 'The message is required.');
    }

    public function testPostCreateFailsIfStatusIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:update:tickets:status',
        ]);
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

    public function testPostCreateFailsIfIsConfidentialIsTrueButAccessIsNotGranted(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'isSolution' => true,
            'isConfidential' => true,
        ]);

        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('#is-confidential-error', 'You are not authorized to answer confidentially.');
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => 'not the token',
            'message' => $messageContent,
        ]);

        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => 'not the token',
            'message' => $messageContent,
        ]);
    }
}
