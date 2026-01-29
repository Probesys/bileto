<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\MessageHandler;

use App\Entity;
use App\Message\CreateTicketsFromMailboxEmails;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\MailboxEmailFactory;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\RoleFactory;
use App\Utils\Time;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CreateTicketsFromMailboxEmailsHandlerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testInvokeCreatesATicketFromAMailboxEmail(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        /** @var HtmlSanitizerInterface */
        $appMessageSanitizer = $container->get('html_sanitizer.sanitizer.app.message_sanitizer');
        $now = Time::freeze();
        $emailDate = Time::ago(1, 'hour');

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
            'date' => $emailDate,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($now->getTimestamp(), $ticket->getCreatedAt()->getTimestamp());
        $this->assertSame($subject, $ticket->getTitle());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
        $this->assertSame($user->getId(), $ticket->getRequester()->getId());

        $message = MessageFactory::first();
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($emailDate->getTimestamp(), $message->getPostedAt()->getTimestamp());
        $sanitizedBody = trim($appMessageSanitizer->sanitize($body));
        $this->assertSame($sanitizedBody, $message->getContent());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('email', $message->getVia());
        $this->assertEquals(
            [$mailboxEmail->getMessageId()],
            $message->getEmailNotificationsReferences()
        );
        Time::unfreeze();
    }

    public function testInvokeCreatesATicketAndCanAttachAContractIfItExists(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        // Log the user so the created Contract has a createdBy and a updatedBy
        // fields set.
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);
        $ongoingContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Time::ago(1, 'week'),
            'endAt' => Time::fromNow(1, 'week'),
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $ticket = TicketFactory::first();
        $ticketContract = $ticket->getOngoingContract();
        $this->assertNotNull($ticketContract);
        $this->assertSame($ongoingContract->getId(), $ticketContract->getId());
    }

    public function testInvokeCreatesATicketAndCanAttachOrganizationObservers(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $observer = UserFactory::createOne();
        $organization = OrganizationFactory::createOne([
            'observers' => [$observer],
        ]);
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $ticket = TicketFactory::first();
        $ticketObservers = $ticket->getObservers();
        $this->assertSame(1, count($ticketObservers));
        $this->assertSame($observer->getUid(), $ticketObservers[0]->getUid());
    }

    public function testInvokeCreatesATicketAndCanAssignToATeam(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $team = TeamFactory::createOne([
            'isResponsible' => true,
        ]);
        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $this->grantTeam($team->_real(), ['orga:create:tickets'], $organization->_real());
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $ticket = TicketFactory::first();
        $assignedTeam = $ticket->getTeam();
        $this->assertNotNull($assignedTeam);
        $this->assertSame($team->getUid(), $assignedTeam->getUid());
    }

    public function testInvokeAnswersToTicketIfTicketIdIsGiven(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        /** @var HtmlSanitizerInterface */
        $appMessageSanitizer = $container->get('html_sanitizer.sanitizer.app.message_sanitizer');

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $assignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
            'requester' => $user,
            'assignee' => $assignee,
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $message = MessageFactory::first();
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $sanitizedBody = trim($appMessageSanitizer->sanitize($body));
        $this->assertSame($sanitizedBody, $message->getContent());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('email', $message->getVia());

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'To', $assignee->getEmail());
    }

    public function testInvokeAnswersToTicketIfInReplyToRepliesToExistingMessage(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        /** @var HtmlSanitizerInterface */
        $appMessageSanitizer = $container->get('html_sanitizer.sanitizer.app.message_sanitizer');

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $emailId = 'abc@example.com';
        $assignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
            'requester' => $user,
            'assignee' => $assignee,
        ]);
        MessageFactory::createOne([
            'ticket' => $ticket,
            'notificationsReferences' => ["email:{$emailId}"],
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'inReplyTo' => $emailId,
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(2, MessageFactory::count());

        $message = MessageFactory::last();
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $sanitizedBody = trim($appMessageSanitizer->sanitize($body));
        $this->assertSame($sanitizedBody, $message->getContent());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('email', $message->getVia());

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'To', $assignee->getEmail());
    }

    public function testInvokeAnswersToTicketIfInReplyToCorrespondsToGlpiAnswers(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        /** @var HtmlSanitizerInterface */
        $appMessageSanitizer = $container->get('html_sanitizer.sanitizer.app.message_sanitizer');

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $uid = 'm1Li5KGHCCfzgAygETyCTWJBHMzrSeXIR5mirM4n';
        $emailId = "GLPI_{$uid}-Ticket-42@example.com";
        $inReplyTo = "GLPI_{$uid}-Ticket-42/add_followup.1708632638.137855826@example.com";
        $assignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
            'requester' => $user,
            'assignee' => $assignee,
        ]);
        MessageFactory::createOne([
            'ticket' => $ticket,
            'notificationsReferences' => ["glpi:{$emailId}"],
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'inReplyTo' => $inReplyTo,
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(2, MessageFactory::count());

        $message = MessageFactory::last();
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $sanitizedBody = trim($appMessageSanitizer->sanitize($body));
        $this->assertSame($sanitizedBody, $message->getContent());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('email', $message->getVia());

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'To', $assignee->getEmail());
    }

    public function testInvokeAnswersToTicketIfSenderIsActorOfTheTicket(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => null,
        ]);
        // Note that we don't grant any permission to the user: being actor of
        // the ticket is enough to answer by email.
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
            'observers' => [$user],
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $message = MessageFactory::first();
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertSame('email', $message->getVia());
    }

    public function testInvokeCreatesATicketIfTicketIdIsGivenButPermissionsAreInsufficient(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        /** @var HtmlSanitizerInterface */
        $appMessageSanitizer = $container->get('html_sanitizer.sanitizer.app.message_sanitizer');

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(2, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::last();
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($subject, $ticket->getTitle());
    }

    public function testInvokeCreatesATicketIfTicketIdIsGivenButTicketIsClosed(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        /** @var HtmlSanitizerInterface */
        $appMessageSanitizer = $container->get('html_sanitizer.sanitizer.app.message_sanitizer');

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $ticket = TicketFactory::createOne([
            'status' => 'closed',
            'organization' => $organization,
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(2, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::last();
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($subject, $ticket->getTitle());
    }

    public function testInvokeCreatesATicketIfAccessIsForbiddenWhenAnswering(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $organization = OrganizationFactory::createOne();
        $otherOrganization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        // The user has the expected permissions on its organization.
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        // But the ticket is associated to another organization!
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $otherOrganization,
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(2, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::last();
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($subject, $ticket->getTitle());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
    }

    public function testInvokeUsesDomainOrganizationIfRequesterDoesNotHaveDefaultOrganization(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $email = 'alix@example.com';
        $domain = 'example.com';
        $organization = OrganizationFactory::createOne([
            'domains' => [$domain],
        ]);
        $user = UserFactory::createOne([
            'email' => $email,
            'organization' => null,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
        $this->assertSame($user->getId(), $ticket->getRequester()->getId());
    }

    public function testInvokeCreatesRequesterIfDoesNotExistButEmailDomainIsHandledByOrganization(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $email = 'alix@example.com';
        $domain = 'example.com';
        $organization = OrganizationFactory::createOne([
            'domains' => [$domain],
        ]);
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $email,
        ]);
        $role = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
            'permissions' => [
                'orga:see',
                'orga:create:tickets',
            ],
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
        $this->assertSame(0, UserFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());
        $this->assertSame(1, UserFactory::count());

        $user = UserFactory::last();
        $this->assertSame($email, $user->getEmail());
        $this->assertNull($user->getOrganization());

        $ticket = TicketFactory::first();
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($user->getId(), $ticket->getRequester()->getId());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
    }

    public function testInvokeAddsAdditionalRecipientsAsObservers(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        /** @var HtmlSanitizerInterface */
        $appMessageSanitizer = $container->get('html_sanitizer.sanitizer.app.message_sanitizer');

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), [
            'orga:create:tickets',
        ], $organization->_real());
        $assignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
            'requester' => $user,
            'assignee' => $assignee,
        ]);
        /** @var string */
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $defaultAppFrom = 'support@example.com';
        $recipient1 = 'alix@example.com';
        $recipient2 = 'charlie@example.com';
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'to' => [$defaultAppFrom, $recipient1],
            'cc' => [$recipient2],
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $observers = $ticket->getObservers()->toArray();
        $this->assertSame(2, count($observers));
        foreach ($observers as $observer) {
            $observerEmail = $observer->getEmail();

            // Note that $defaultAppFrom is not part of the observers as it is the
            // email address of the application.
            $this->assertContains($observerEmail, [$recipient1, $recipient2]);

            $authorizations = $observer->getAuthorizations();
            $this->assertSame(0, count($authorizations));
        }
    }

    #[DataProvider('autoreplyHeadersProvider')]
    public function testInvokeIgnoresAutoreplyEmails(string $headerName, string $headerValue): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $organization->_real());
        $subject = \Zenstruck\Foundry\faker()->words(3, true);
        $body = \Zenstruck\Foundry\faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
            'headers' => [
                $headerName => $headerValue,
            ],
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());
    }

    public function testInvokeFailsIfRequesterDoesNotExists(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => \Zenstruck\Foundry\faker()->email(),
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $mailboxEmail->_refresh();
        $this->assertSame('unknown sender', $mailboxEmail->getLastError());
    }

    public function testInvokeFailsIfRequesterHasNoOrganization(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $user = UserFactory::createOne([
            'organization' => null,
        ]);
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $mailboxEmail->_refresh();
        $this->assertSame('sender has not permission to create tickets', $mailboxEmail->getLastError());
    }

    public function testInvokeFailsIfAccessIsForbidden(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => $user->getEmail(),
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $mailboxEmail->_refresh();
        $this->assertSame('sender has not permission to create tickets', $mailboxEmail->getLastError());
    }

    /**
     * @return array<array{string, string}>
     */
    public static function autoreplyHeadersProvider(): array
    {
        return [
            ['Auto-Submitted', 'auto-generated'],
            ['Auto-Submitted', 'auto-replied'],
            ['Auto-Submitted', 'auto-notified'],
            ['X-Autoreply', 'yes'],
            ['X-Autorespond', 'yes'],
            ['X-Autoresponder', 'yes'],
            ['Precedence', 'auto_reply'],
            ['X-Precedence', 'auto_reply'],
            ['Delivered-To', 'autoresponder'],
        ];
    }
}
