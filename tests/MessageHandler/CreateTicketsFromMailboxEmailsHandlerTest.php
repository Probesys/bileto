<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\MessageHandler;

use App\Message\CreateTicketsFromMailboxEmails;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MailboxEmailFactory;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
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

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->object(), ['orga:create:tickets'], $organization->object());
        $date = Factory::faker()->dateTime();
        $subject = Factory::faker()->words(3, true);
        $body = Factory::faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'date' => $date,
            'from' => $user->getEmail(),
            'subject' => $subject,
            'htmlBody' => $body,
        ]);

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertEquals($date, $ticket->getCreatedAt());
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($subject, $ticket->getTitle());
        $this->assertSame($organization->getId(), $ticket->getOrganization()->getId());
        $this->assertSame($user->getId(), $ticket->getRequester()->getId());

        $message = MessageFactory::first();
        $this->assertEquals($date, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $sanitizedBody = trim($appMessageSanitizer->sanitize($body));
        $this->assertSame($sanitizedBody, $message->getContent());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('email', $message->getVia());
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
        $this->grantOrga($user->object(), ['orga:create:tickets:messages'], $organization->object());
        $assignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
            'requester' => $user,
            'assignee' => $assignee,
        ]);
        $date = Factory::faker()->dateTime();
        /** @var string */
        $subject = Factory::faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = Factory::faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'date' => $date,
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
        $this->assertEquals($date, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $sanitizedBody = trim($appMessageSanitizer->sanitize($body));
        $this->assertSame($sanitizedBody, $message->getContent());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('email', $message->getVia());

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'Bcc', $assignee->getEmail());
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
        $this->grantOrga($user->object(), ['orga:create:tickets:messages'], $organization->object());
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
            'emailId' => $emailId,
        ]);
        $date = Factory::faker()->dateTime();
        /** @var string */
        $subject = Factory::faker()->words(3, true);
        $body = Factory::faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'date' => $date,
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
        $this->assertEquals($date, $message->getCreatedAt());
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
        $sanitizedBody = trim($appMessageSanitizer->sanitize($body));
        $this->assertSame($sanitizedBody, $message->getContent());
        $this->assertSame($ticket->getId(), $message->getTicket()->getId());
        $this->assertFalse($message->isConfidential());
        $this->assertSame('email', $message->getVia());

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'Bcc', $assignee->getEmail());
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
        $this->grantOrga($user->object(), ['orga:create:tickets'], $organization->object());
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $organization,
        ]);
        $date = Factory::faker()->dateTime();
        /** @var string */
        $subject = Factory::faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = Factory::faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'date' => $date,
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
        $this->grantOrga($user->object(), ['orga:create:tickets'], $organization->object());
        $ticket = TicketFactory::createOne([
            'status' => 'closed',
            'organization' => $organization,
        ]);
        $date = Factory::faker()->dateTime();
        /** @var string */
        $subject = Factory::faker()->words(3, true);
        $subject = "Re: [#{$ticket->getId()}] " . $subject;
        $body = Factory::faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'date' => $date,
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

    public function testInvokeConsidersReplyToInPriority(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $organization = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization,
        ]);
        $this->grantOrga($user->object(), ['orga:create:tickets'], $organization->object());
        $date = Factory::faker()->dateTime();
        $subject = Factory::faker()->words(3, true);
        $body = Factory::faker()->randomHtml();
        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => UserFactory::createOne()->getEmail(),
            'replyTo' => $user->getEmail(),
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(0, MailboxEmailFactory::count());
        $this->assertSame(1, TicketFactory::count());
        $this->assertSame(1, MessageFactory::count());

        $ticket = TicketFactory::first();
        $this->assertSame($user->getId(), $ticket->getCreatedBy()->getId());
        $this->assertSame($user->getId(), $ticket->getRequester()->getId());

        $message = MessageFactory::first();
        $this->assertSame($user->getId(), $message->getCreatedBy()->getId());
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
        $this->grantOrga($user->object(), [
            'orga:create:tickets',
            'orga:create:tickets:messages',
        ], $organization->object());
        // But the ticket is associated to another organization!
        $ticket = TicketFactory::createOne([
            'status' => 'new',
            'organization' => $otherOrganization,
        ]);
        /** @var string */
        $subject = Factory::faker()->words(3, true);
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

    public function testInvokeFailsIfRequesterDoesNotExists(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $mailboxEmail = MailboxEmailFactory::createOne([
            'from' => Factory::faker()->email(),
        ]);

        $bus->dispatch(new CreateTicketsFromMailboxEmails());

        $this->assertSame(1, MailboxEmailFactory::count());
        $this->assertSame(0, TicketFactory::count());
        $this->assertSame(0, MessageFactory::count());

        $mailboxEmail->refresh();
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

        $mailboxEmail->refresh();
        $this->assertSame('sender is not attached to an organization', $mailboxEmail->getLastError());
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

        $mailboxEmail->refresh();
        $this->assertSame('sender has not permission to create tickets', $mailboxEmail->getLastError());
    }
}
