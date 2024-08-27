<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'assignee' => $user,
            'status' => Foundry\faker()->randomElement(Ticket::OPEN_STATUSES),
        ]);

        $client->request(Request::METHOD_GET, '/tickets');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="ticket-item"]', "#{$ticket->getId()} My ticket");
    }

    public function testGetNewRendersCorrectlyIfManyOrganizationsAreAccessible(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        OrganizationFactory::createMany(2);
        $this->grantOrga($user->_real(), ['orga:see', 'orga:create:tickets']);

        $client->request(Request::METHOD_GET, '/tickets/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New ticket');
    }

    public function testGetNewRedirectsIfUserCanCreateTicketInItsDefaultOrganization(): void
    {
        $client = static::createClient();
        list($orga1, $orga2) = OrganizationFactory::createMany(2);
        $user = UserFactory::createOne([
            'organization' => $orga1,
        ]);
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets']);

        $client->request(Request::METHOD_GET, '/tickets/new');

        $this->assertResponseRedirects("/organizations/{$orga1->getUid()}/tickets/new", 302);
    }

    public function testGetNewRedirectsIfOnlyOneOrganizationIsAccessible(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        list($orga1, $orga2) = OrganizationFactory::createMany(2);
        $this->grantOrga($user->_real(), ['orga:create:tickets'], $orga1->_real());

        $client->request(Request::METHOD_GET, '/tickets/new');

        $this->assertResponseRedirects("/organizations/{$orga1->getUid()}/tickets/new", 302);
    }

    public function testGetNewRedirectsIfOrganizationIsGiven(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        list($orga1, $orga2) = OrganizationFactory::createMany(2);
        $this->grantOrga($user->_real(), ['orga:create:tickets']);

        $client->request(Request::METHOD_GET, '/tickets/new', [
            'organization' => $orga2->getUid(),
        ]);

        $this->assertResponseRedirects("/organizations/{$orga2->getUid()}/tickets/new", 302);
    }

    public function testGetNewFailsIfGivenOrganizationDoesNotExist(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        list($orga1, $orga2) = OrganizationFactory::createMany(2);
        $this->grantOrga($user->_real(), ['orga:create:tickets']);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/tickets/new', [
            'organization' => 'not an uid',
        ]);
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        list($orga1, $orga2) = OrganizationFactory::createMany(2);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/tickets/new');
    }

    public function testGetShowRendersCorrectlyIfTicketIsCreatedByUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all']);
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "#{$ticket->getId()} My ticket");
    }

    public function testGetShowRendersMessages(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);
        $this->grantOrga($user->_real(), ['orga:see']);
        $content = 'The content of the answer';
        $message = MessageFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), [
            'orga:see',
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

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="message-item"]', $content);
    }

    public function testGetShowHidesConfidentialMessagesIfAccessIsNotGranted(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all']);
        $content = 'The content of the answer';
        $ticket = TicketFactory::createOne();
        $message = MessageFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
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
        $ticketRepository = $entityManager->getRepository(Ticket::class);
        $ticketRepository->save($ticket->_real(), true);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="event-item"]', 'The old title');
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");
    }

    public function testGetShowRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseRedirects('/login', 302);
    }
}
