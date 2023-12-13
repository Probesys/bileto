<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Entity\Ticket;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\MessageDocumentFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\TimeSpentFactory;
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
        $this->assertMatchesRegularExpression('/^[\w\d]*@example.com$/', $message->getEmailId());
        $this->assertSame('pending', $ticket->getStatus());
        $this->assertEquals($now, $ticket->getUpdatedAt());
        $this->assertsame($user->getId(), $ticket->getUpdatedBy()->getId());
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHtmlBodyContains($email, $messageContent);
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

    public function testPostCreateAttachesDocumentsToMessage(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message <style>body { background-color: pink; }</style>';
        list($messageDocument1, $messageDocument2) = MessageDocumentFactory::createMany(2, [
            'createdBy' => $user->object(),
            'message' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
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

    public function testPostCreateAcceptsTimeSpent(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'timeSpent' => 20,
        ]);

        $timeSpent = TimeSpentFactory::first();
        $this->assertSame(20, $timeSpent->getTime());
        $this->assertSame($ticket->getId(), $timeSpent->getTicket()->getId());
    }

    public function testPostCanAssociateTimeSpentToContract(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $contract = ContractFactory::createOne([
            'startAt' => Time::ago(1, 'week'),
            'endAt' => Time::fromNow(1, 'week'),
            'maxHours' => 10,
            'billingInterval' => 30,
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $ticket->addContract($contract->object());
        $messageContent = 'My message';

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'timeSpent' => 10,
        ]);

        $timeSpent = TimeSpentFactory::first();
        $this->assertSame(30, $timeSpent->getTime());
        $this->assertSame(10, $timeSpent->getRealTime());
        $this->assertSame($ticket->getId(), $timeSpent->getTicket()->getId());
        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
    }

    public function testPostCreateCanCreateTwoTimeSpentIfContractIsAlmostFinished(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $contract = ContractFactory::createOne([
            'startAt' => Time::ago(1, 'week'),
            'endAt' => Time::fromNow(1, 'week'),
            'maxHours' => 1,
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $ticket->addContract($contract->object());
        $messageContent = 'My message';

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'timeSpent' => 80,
        ]);

        list($timeSpent1, $timeSpent2) = TimeSpentFactory::all();
        $this->assertSame(60, $timeSpent1->getTime());
        $this->assertSame($ticket->getId(), $timeSpent1->getTicket()->getId());
        $this->assertSame($contract->getId(), $timeSpent1->getContract()->getId());
        $this->assertSame(20, $timeSpent2->getTime());
        $this->assertSame($ticket->getId(), $timeSpent2->getTicket()->getId());
        $this->assertNull($timeSpent2->getContract());
    }

    public function testPostCreateChangesStatusToInProgressIfUserIsRequester(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'status' => 'pending',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->getStatus());
    }

    public function testPostCreateChangesStatusToPendingStatusIfUserIsAssignee(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => 'in_progress',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);

        $ticket->refresh();
        $this->assertSame('pending', $ticket->getStatus());
    }

    public function testPostCreateSetsSolutionIfActionIsNewSolutionAndUserIsAssignee(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = Factory::faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerAction' => 'new solution',
        ]);

        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $ticket->refresh();
        $this->assertNotNull($message);
        $this->assertNotNull($ticket->getSolution());
        $this->assertSame($message->getId(), $ticket->getSolution()->getId());
        $this->assertSame('resolved', $ticket->getStatus());
    }

    public function testPostCreateDoesNotSetSolutionIfIsConfidentialIsTrue(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:messages:confidential',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerAction' => 'new solution',
            'isConfidential' => true,
        ]);

        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $this->assertTrue($message->isConfidential());
        $ticket->refresh();
        $this->assertNull($ticket->getSolution());
    }

    public function testPostCreateDoesNotChangeSolutionIfAlreadyExists(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:messages:confidential',
        ]);
        $solution = MessageFactory::createOne();
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'solution' => $solution,
        ]);
        $messageContent = 'My message';

        $this->assertSame(1, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerAction' => 'new solution',
        ]);

        $this->assertSame(2, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::last();
        $this->assertNotSame($solution->getId(), $message->getId());
        $ticket->refresh();
        $this->assertSame($solution->getId(), $ticket->getSolution()->getId());
    }

    public function testPostCreateDoesNotSetSolutionIfStatusIsFinished(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = 'closed';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerAction' => 'new solution',
        ]);

        $ticket->refresh();
        $this->assertSame($initialStatus, $ticket->getStatus());
    }

    public function testPostCreateDoesNotSetSolutionIfUserIsRequester(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerAction' => 'new solution',
        ]);

        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertNull($ticket->getSolution());
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
        $this->assertSelectorTextContains('#message-error', 'Enter a message');
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
        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
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
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);
    }

    public function testPostCreateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne();
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);
    }
}
