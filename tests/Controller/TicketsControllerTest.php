<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Entity\Ticket;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request('GET', '/tickets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersCorrectlyIfTicketIsCreatedByUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersCorrectlyIfTicketIsRequestedByUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'requester' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersCorrectlyIfTicketIsAssignedToUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'assignee' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersCorrectlyIfAccessIsGranted(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:see:tickets:all']);
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersMessages(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);
        $content = 'The content of the answer';
        $message = MessageFactory::createOne([
            'isConfidential' => false,
            'ticket' => $ticket,
            'content' => $content
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="message-item"]', $content);
    }

    public function testGetShowRendersConfidentialMessagesIfAccessIsGranted(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), [
            'orga:see:tickets:all',
            'orga:see:tickets:messages:confidential',
        ]);
        $content = 'The content of the answer';
        $ticket = TicketFactory::createOne();
        $message = MessageFactory::createOne([
            'isConfidential' => true,
            'ticket' => $ticket,
            'content' => $content
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="message-item"]', $content);
    }

    public function testGetShowHidesConfidentialMessagesIfAccessIsNotGranted(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:see:tickets:all']);
        $content = 'The content of the answer';
        $ticket = TicketFactory::createOne();
        $message = MessageFactory::createOne([
            'isConfidential' => true,
            'ticket' => $ticket,
            'content' => $content
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-test="message-item"]');
    }

    public function testGetShowRendersEvents(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'The old title',
            'createdBy' => $user,
        ]);
        // Change the title and save the ticket to create a new EntityEvent in
        // database.
        $ticket->setTitle('The new title');
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var \App\Repository\TicketRepository $ticketRepository */
        $ticketRepository = $entityManager->getRepository(Ticket::class);
        $ticketRepository->save($ticket->object(), true);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="event-item"]', 'The old title');
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/tickets/{$ticket->getUid()}");
    }

    public function testGetShowRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseRedirects('http://localhost/login', 302);
    }
}
