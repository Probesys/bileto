<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry;
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'pending',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");
        $crawler = $client->submitForm('form-create-message-submit', [
            'message' => $messageContent,
        ]);

        Time::unfreeze();
        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $ticket->_refresh();
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

    public function testPostCreateCanCreateConfidentialMessage(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:messages:confidential',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'pending',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'confidential',
        ]);

        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertTrue($message->isConfidential());
        $this->assertSame('pending', $ticket->getStatus());
    }

    public function testPostCreateSanitizesTheMessageContent(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message <style>body { background-color: pink; }</style>';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message <style>body { background-color: pink; }</style>';
        list($messageDocument1, $messageDocument2) = MessageDocumentFactory::createMany(2, [
            'createdBy' => $user->_real(),
            'message' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'messageDocumentUids' => [
                $messageDocument1->getUid(),
                $messageDocument2->getUid(),
            ],
        ]);

        $message = MessageFactory::first();
        $messageDocument1->_refresh();
        $messageDocument2->_refresh();
        $this->assertNotNull($message);
        $this->assertSame($message->getUid(), $messageDocument1->getMessage()->getUid());
        $this->assertSame($message->getUid(), $messageDocument2->getMessage()->getUid());
    }

    public function testPostCreateAcceptsTimeSpent(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $contract = ContractFactory::createOne([
            'startAt' => Time::ago(1, 'week'),
            'endAt' => Time::fromNow(1, 'week'),
            'maxHours' => 10,
            'timeAccountingUnit' => 30,
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $ticket->addContract($contract->_real());
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
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
        $ticket->addContract($contract->_real());
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'status' => 'pending',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);

        $ticket->_refresh();
        $this->assertSame('in_progress', $ticket->getStatus());
    }

    public function testPostCreateChangesStatusToPendingStatusIfUserIsAssignee(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => 'in_progress',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);

        $ticket->_refresh();
        $this->assertSame('pending', $ticket->getStatus());
    }

    public function testPostCreateSetsSolutionIfActionIsNewSolutionAndUserIsAssignee(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = Foundry\faker()->randomElement(Ticket::OPEN_STATUSES);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'solution',
        ]);

        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::first();
        $ticket->_refresh();
        $this->assertNotNull($message);
        $this->assertNotNull($ticket->getSolution());
        $this->assertSame($message->getId(), $ticket->getSolution()->getId());
        $this->assertSame('resolved', $ticket->getStatus());
    }

    public function testPostCreateDoesNotChangeSolutionIfAlreadyExists(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
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

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'solution',
        ]);

        $this->assertSame(2, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = MessageFactory::last();
        $this->assertNotSame($solution->getId(), $message->getId());
        $ticket->_refresh();
        $this->assertSame($solution->getId(), $ticket->getSolution()->getId());
    }

    public function testPostCreateDoesNotSetSolutionIfStatusIsFinished(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
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

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'solution',
        ]);

        $ticket->_refresh();
        $this->assertSame($initialStatus, $ticket->getStatus());
    }

    public function testPostCreateDoesNotSetSolutionIfUserIsRequester(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'solution',
        ]);

        $this->assertSame(1, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertNull($ticket->getSolution());
    }

    public function testPostCreateRefusesSolutionIfActionIsRefuseSolutionAndUserIsRequester(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = 'resolved';
        $solution = MessageFactory::createOne();
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'status' => $initialStatus,
            'solution' => $solution,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'solution refusal',
        ]);

        $this->assertSame(2, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertNull($ticket->getSolution());
        $this->assertSame('in_progress', $ticket->getStatus());
    }

    public function testPostCreateApprovesSolutionIfActionIsApproveSolutionAndUserIsRequester(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = 'resolved';
        $solution = MessageFactory::createOne();
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'status' => $initialStatus,
            'solution' => $solution,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'solution approval',
        ]);

        $this->assertSame(2, MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertNotNull($ticket->getSolution());
        $this->assertSame($solution->getId(), $ticket->getSolution()->getId());
        $this->assertSame('closed', $ticket->getStatus());
    }

    public function testPostCreateFailsIfMessageIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = '';

        $this->assertSame(0, MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
            'answerType' => 'confidential',
        ]);

        $this->assertSame(0, MessageFactory::count());
        $this->assertSelectorTextContains(
            '#answer-type-error',
            'You are not authorized to answer confidentially.'
        );
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
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
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);
    }

    public function testPostCreateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = TicketFactory::createOne();
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create ticket message'),
            'message' => $messageContent,
        ]);
    }
}
