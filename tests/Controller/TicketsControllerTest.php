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

    public function testGetCsvRendersCsv(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all'], $organization->_real());
        $ticket1 = Factory\TicketFactory::createOne([
            'title' => 'First ticket',
            'organization' => $organization,
            'status' => 'new',
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'title' => 'Second ticket',
            'organization' => $organization,
            'status' => 'new',
        ]);

        $client->request(Request::METHOD_GET, '/tickets.csv');
        $content = $client->getInternalResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
        $contentDisposition = $client->getResponse()->headers->get('Content-Disposition') ?? '';
        $this->assertStringStartsWith('attachment; filename="bileto-tickets-', $contentDisposition);
        $this->assertStringContainsString('First ticket', $content);
        $this->assertStringContainsString('Second ticket', $content);
        $this->assertStringContainsString((string) $ticket1->getId(), $content);
        $this->assertStringContainsString((string) $ticket2->getId(), $content);
    }

    public function testGetCsvIsScopedToUserOrganizations(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization1 = Factory\OrganizationFactory::createOne();
        $organization2 = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all'], $organization1->_real());
        Factory\TicketFactory::createOne([
            'title' => 'Authorized ticket',
            'organization' => $organization1,
            'status' => 'new',
        ]);
        Factory\TicketFactory::createOne([
            'title' => 'Forbidden ticket',
            'organization' => $organization2,
            'status' => 'new',
        ]);

        $client->request(Request::METHOD_GET, '/tickets.csv');
        $content = $client->getInternalResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Authorized ticket', $content);
        $this->assertStringNotContainsString('Forbidden ticket', $content);
    }

    public function testGetCsvAppliesQueryAndSort(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all'], $organization->_real());
        Factory\TicketFactory::createOne([
            'title' => 'Apple ticket',
            'organization' => $organization,
            'status' => 'closed',
        ]);
        Factory\TicketFactory::createOne([
            'title' => 'Banana ticket',
            'organization' => $organization,
            'status' => 'closed',
        ]);
        Factory\TicketFactory::createOne([
            'title' => 'Cherry ticket',
            'organization' => $organization,
            'status' => 'new',
        ]);

        $client->request(Request::METHOD_GET, '/tickets.csv?q=status%3Aclosed&sort=title-asc');
        $content = $client->getInternalResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Apple ticket', $content);
        $this->assertStringContainsString('Banana ticket', $content);
        $this->assertStringNotContainsString('Cherry ticket', $content);
        $this->assertLessThan(strpos($content, 'Banana ticket'), strpos($content, 'Apple ticket'));
    }

    public function testGetCsvAppliesView(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all'], $organization->_real());
        Factory\TicketFactory::createOne([
            'title' => 'Ticket owned by me',
            'organization' => $organization,
            'requester' => $user,
            'status' => 'new',
        ]);
        Factory\TicketFactory::createOne([
            'title' => 'Ticket owned by other',
            'organization' => $organization,
            'status' => 'new',
        ]);

        $client->request(Request::METHOD_GET, '/tickets.csv?view=owned');
        $content = $client->getInternalResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Ticket owned by me', $content);
        $this->assertStringNotContainsString('Ticket owned by other', $content);
    }

    public function testGetCsvUsesUserLocale(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne(['locale' => 'fr_FR']);
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:tickets:all'], $organization->_real());
        Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'organization' => $organization,
            'status' => 'new',
        ]);
        $session = $this->getSession($client);
        $session->set('_locale', 'fr_FR');
        $session->save();

        $client->request(Request::METHOD_GET, '/tickets.csv');
        $content = $client->getInternalResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Référence', $content);
        $this->assertStringContainsString('Créé le', $content);
        $this->assertStringNotContainsString('Reference', $content);
        $this->assertStringNotContainsString('Created at', $content);
    }

    public function testGetCsvRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/tickets.csv');

        $this->assertResponseRedirects('/login', 302);
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

    public function testGetShowRendersPinnedInformation(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $information = 'Lorem ipsum';
        $organization = Factory\OrganizationFactory::createOne([
            'pinnedInformation' => $information,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
            'organization' => $organization,
        ]);
        $this->grantOrga($user->_real(), ['orga:see', 'orga:see:pinned_information']);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="organization-pinned-information"]', $information);
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
