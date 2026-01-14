<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity;
use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\SessionHelper;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'assignee' => $user,
            'status' => Foundry\faker()->randomElement(Entity\Ticket::OPEN_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, '/tickets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', "#{$ticket->getId()} My ticket");
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets']);

        $client->request(Request::METHOD_GET, '/tickets/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New ticket');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/tickets/new');
    }

    public function testPostNewRedirectsToTheSelectedOrganization(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:create:tickets']);

        $client->request(Request::METHOD_POST, '/tickets/new', [
            'ticket' => [
                '_token' => $this->generateCsrfToken($client, 'organization select'),
                'organization' => $organization->getId(),
            ],
        ]);

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/tickets/new", 302);
    }

    public function testGetShowRendersCorrectlyIfTicketIsCreatedByUser(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "#{$ticket->getId()} My ticket");
    }

    public function testGetShowRendersCorrectlyIfTicketIsRequestedByUser(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'requester' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "#{$ticket->getId()} My ticket");
    }

    public function testGetShowRendersCorrectlyIfTicketIsAssignedToUser(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'assignee' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "#{$ticket->getId()} My ticket");
    }

    public function testGetShowRendersCorrectlyIfUserIsObserverOfTheTicket(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'observers' => [$user],
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "#{$ticket->getId()} My ticket");
    }

    public function testGetShowRendersCorrectlyIfAccessIsGranted(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all']);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "#{$ticket->getId()} My ticket");
    }

    public function testGetShowRendersMessages(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);
        $this->grantOrga($user->_real(), ['orga:see']);
        $content = 'The content of the answer';
        $message = Factory\MessageFactory::createOne([
            'isConfidential' => false,
            'ticket' => $ticket,
            'content' => $content
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="message-item"]', $content);
    }

    public function testGetShowRendersConfidentialMessagesIfAccessIsGranted(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:see',
            'orga:see:tickets:all',
            'orga:see:tickets:messages:confidential',
        ]);
        $content = 'The content of the answer';
        $ticket = Factory\TicketFactory::createOne();
        $message = Factory\MessageFactory::createOne([
            'isConfidential' => true,
            'ticket' => $ticket,
            'content' => $content
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="message-item"]', $content);
    }

    public function testGetShowHidesConfidentialMessagesIfAccessIsNotGranted(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all']);
        $content = 'The content of the answer';
        $ticket = Factory\TicketFactory::createOne();
        $message = Factory\MessageFactory::createOne([
            'isConfidential' => true,
            'ticket' => $ticket,
            'content' => $content
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-test="message-item"]');
    }

    public function testGetShowRendersEvents(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'The old title',
            'createdBy' => $user,
        ]);
        $this->grantOrga($user->_real(), ['orga:see']);
        // Change the title and save the ticket to create a new EntityEvent in
        // database.
        $ticket->setTitle('The new title');
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var \App\Repository\TicketRepository $ticketRepository */
        $ticketRepository = $entityManager->getRepository(Entity\Ticket::class);
        $ticketRepository->save($ticket->_real(), true);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="event-item"]', 'The old title');
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");
    }

    public function testGetShowRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseRedirects('/login', 302);
    }
}
