<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

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

class MessagesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\SessionHelper;

    public function testPostCreateCreatesAMessageAndRedirects(): void
    {
        $now = new \DateTimeImmutable('2022-11-02');
        Utils\Time::freeze($now);
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'pending',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        Utils\Time::unfreeze();
        $this->assertSame(1, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = Factory\MessageFactory::first();
        $ticket->_refresh();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertEquals($now, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('webapp', $message->getVia());
        $references = $message->getEmailNotificationsReferences();
        $this->assertSame(1, count($references));
        $this->assertMatchesRegularExpression('/^[\w\d]*@example.com$/', $references[0]);
        $this->assertSame('pending', $ticket->getStatus());
        $this->assertEquals($now, $ticket->getUpdatedAt());
        $this->assertsame($user->getId(), $ticket->getUpdatedBy()->getId());
    }

    public function testPostCreateSendsEmailNotification(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $requester = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $requester,
            'status' => 'pending',
        ]);
        $previousEmailId = 'foo@example.com';
        $previousMessage = Factory\MessageFactory::createOne([
            'ticket' => $ticket,
            'createdAt' => Utils\Time::ago(1, 'hour'),
            'isConfidential' => false,
            'notificationsReferences' => ["email:{$previousEmailId}"],
        ]);
        $messageContent = 'My message';

        $this->assertSame(1, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(2, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = Factory\MessageFactory::last();
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'To', $requester->getEmail());
        $this->assertEmailHtmlBodyContains($email, $messageContent);
        $this->assertEmailHasHeader($email, 'References');
        $this->assertEmailHeaderSame($email, 'References', "<{$previousEmailId}>");
        $this->assertEmailHasHeader($email, 'In-Reply-To');
        $this->assertEmailHeaderSame($email, 'In-Reply-To', "<{$previousEmailId}>");
    }

    public function testPostCreateDoesNotSendEmailNotificationToAnonymousUsers(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $requester = Factory\UserFactory::createOne([
            'anonymizedAt' => Utils\Time::now(),
        ]);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $requester,
            'status' => 'pending',
        ]);
        $previousMessage = Factory\MessageFactory::createOne([
            'ticket' => $ticket,
            'createdAt' => Utils\Time::ago(1, 'hour'),
            'isConfidential' => false,
        ]);
        $messageContent = 'My message';

        $this->assertSame(1, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(2, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $this->assertEmailCount(0);
    }

    public function testPostCreateCanCreateConfidentialMessage(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:messages:confidential',
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'pending',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'type' => 'confidential',
            ],
        ]);

        $this->assertSame(1, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = Factory\MessageFactory::first();
        $this->assertSame($messageContent, $message->getContent());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertTrue($message->isConfidential());
        $this->assertSame('pending', $ticket->getStatus());
    }

    public function testPostCreateSanitizesTheMessageContent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);
        $messageContent = 'My message <style>body { background-color: pink; }</style>';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(1, Factory\MessageFactory::count());

        $message = Factory\MessageFactory::first();
        $this->assertSame('My message', $message->getContent());
    }

    public function testPostCreateAttachesDocumentsToMessage(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);
        $messageContent = 'My message <style>body { background-color: pink; }</style>';
        list($messageDocument1, $messageDocument2) = Factory\MessageDocumentFactory::createMany(2, [
            'createdBy' => $user->_real(),
            'message' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $message = Factory\MessageFactory::first();
        $messageDocument1->_refresh();
        $messageDocument2->_refresh();
        $this->assertSame($message->getUid(), $messageDocument1->getMessage()->getUid());
        $this->assertSame($message->getUid(), $messageDocument2->getMessage()->getUid());
    }

    public function testPostCreateAcceptsTimeSpent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'timeSpent' => 20,
            ],
        ]);

        $message = Factory\MessageFactory::last();
        $timeSpent = Factory\TimeSpentFactory::first();
        $this->assertSame(20, $timeSpent->getTime());
        $this->assertSame($ticket->getId(), $timeSpent->getTicket()->getId());
        $this->assertSame($message->getId(), $timeSpent->getMessage()->getId());
    }

    public function testPostCanAssociateTimeSpentToContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $contract = Factory\ContractFactory::createOne([
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
            'maxHours' => 10,
            'timeAccountingUnit' => 30,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'contracts' => [$contract],
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'timeSpent' => 10,
            ],
        ]);

        $timeSpent = Factory\TimeSpentFactory::first();
        $this->assertSame(30, $timeSpent->getTime());
        $this->assertSame(10, $timeSpent->getRealTime());
        $this->assertSame($ticket->getId(), $timeSpent->getTicket()->getId());
        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
    }

    public function testPostCreateCanCreateTwoTimeSpentIfContractIsAlmostFinished(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:time_spent',
        ]);
        $contract = Factory\ContractFactory::createOne([
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
            'maxHours' => 1,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'contracts' => [$contract],
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'timeSpent' => 80,
            ],
        ]);

        list($timeSpent1, $timeSpent2) = Factory\TimeSpentFactory::all();
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
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'status' => 'pending',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $ticket->_refresh();
        $this->assertSame('in_progress', $ticket->getStatus());
    }

    public function testPostCreateChangesStatusToPendingStatusIfUserIsAssignee(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => 'in_progress',
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $ticket->_refresh();
        $this->assertSame('pending', $ticket->getStatus());
    }

    public function testPostCreateSetsSolutionIfActionIsNewSolutionAndUserIsAssignee(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => $initialStatus,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'type' => 'solution',
            ],
        ]);

        $this->assertSame(1, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $message = Factory\MessageFactory::first();
        $ticket->_refresh();
        $this->assertNotNull($ticket->getSolution());
        $this->assertSame($message->getId(), $ticket->getSolution()->getId());
        $this->assertSame('resolved', $ticket->getStatus());
    }

    public function testPostCreateRefusesSolutionByDefaultIfUserIsNotAnAgent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:see:tickets:all',
        ], type: 'user');
        $initialStatus = 'resolved';
        $solution = Factory\MessageFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => $initialStatus,
            'solution' => $solution,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(2, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertNull($ticket->getSolution());
        $this->assertSame('in_progress', $ticket->getStatus());
    }

    public function testPostCreateRefusesSolutionIfActionIsRefuseSolution(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = 'resolved';
        $solution = Factory\MessageFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'status' => $initialStatus,
            'solution' => $solution,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'solutionAction' => 'refuse',
            ],
        ]);

        $this->assertSame(2, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertNull($ticket->getSolution());
        $this->assertSame('in_progress', $ticket->getStatus());
    }

    public function testPostCreateApprovesSolutionIfActionIsApprove(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $initialStatus = 'resolved';
        $solution = Factory\MessageFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $user,
            'status' => $initialStatus,
            'solution' => $solution,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'solutionAction' => 'approve',
            ],
        ]);

        $this->assertSame(2, Factory\MessageFactory::count());

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertNotNull($ticket->getSolution());
        $this->assertSame($solution->getId(), $ticket->getSolution()->getId());
        $this->assertSame('closed', $ticket->getStatus());
    }

    public function testPostCreateFailsIfMessageIsEmpty(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);
        $messageContent = '';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#answer_content-error', 'Enter a message');
    }

    public function testPostCreateFailsIfIsConfidentialIsTrueButAccessIsNotGranted(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'type' => 'confidential',
            ],
        ]);

        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#answer_type-error', 'The selected choice is invalid');
    }

    public function testPostCreateFailsIfSolutionAlreadyExists(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
            'orga:create:tickets:messages:confidential',
        ]);
        $solution = Factory\MessageFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'resolved',
            'createdBy' => $user,
            'assignee' => $user,
            'solution' => $solution,
        ]);
        $messageContent = 'My message';

        $this->assertSame(1, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'type' => 'solution',
            ],
        ]);

        $this->assertSame(1, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#answer_type-error', 'The selected choice is invalid');
    }

    public function testPostCreateFailsIfSettingSolutionAndUserIsNotAssignee(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:create:tickets:messages',
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'assignee' => null,
        ]);
        $messageContent = 'My message';

        $this->assertSame(0, Factory\MessageFactory::count());

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
                'type' => 'solution',
            ],
        ]);

        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#answer_type-error', 'The selected choice is invalid');
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => 'not the token',
                'content' => $messageContent,
            ],
        ]);

        $this->assertSame(0, Factory\MessageFactory::count());
        $this->assertSelectorTextContains('#answer-error', 'The security token is invalid');
    }

    public function testPostCreateFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);
    }

    public function testPostCreateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:messages']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
        ]);
        $messageContent = 'My message';

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/messages/new", [
            'answer' => [
                '_token' => $this->generateCsrfToken($client, 'answer'),
                'content' => $messageContent,
            ],
        ]);
    }
}
