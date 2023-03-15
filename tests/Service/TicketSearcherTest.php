<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Entity\Ticket;
use App\Service\TicketSearcher;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketSearcherTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetTicketsReturnsTicketCreatedByUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket->getId(), $tickets[0]->getId());
    }

    public function testGetTicketsReturnsTicketAssignedToUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'assignee' => $user,
        ]);

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket->getId(), $tickets[0]->getId());
    }

    public function testGetTicketsReturnsTicketRequestedByUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'requester' => $user,
        ]);

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket->getId(), $tickets[0]->getId());
    }

    public function testGetTicketsDoesNotReturnTicketNotInvolvingUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne();

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(0, count($tickets));
    }

    public function testGetTicketsCanRestrictToAGivenOrganization(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization1 = OrganizationFactory::createOne();
        $organization2 = OrganizationFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'createdBy' => $user,
            'organization' => $organization1,
        ]);
        $ticket2 = TicketFactory::createOne([
            'createdBy' => $user,
            'organization' => $organization2,
        ]);
        $ticketSearcher->setOrganization($organization1->object());

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket1->getId(), $tickets[0]->getId());
    }

    public function testGetTicketsCanRestrictToAListOfGivenOrganizations(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization1 = OrganizationFactory::createOne();
        $organization2 = OrganizationFactory::createOne();
        $organization3 = OrganizationFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'createdBy' => $user,
            'organization' => $organization1,
        ]);
        $ticket2 = TicketFactory::createOne([
            'createdBy' => $user,
            'organization' => $organization2,
        ]);
        $ticket3 = TicketFactory::createOne([
            'createdBy' => $user,
            'organization' => $organization3,
        ]);
        $ticketSearcher->setOrganizations([
            $organization1->object(),
            $organization2->object(),
        ]);

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(2, count($tickets));
        $ticketIds = array_map(function ($ticket) {
            return $ticket->getId();
        }, $tickets);
        $this->assertContains($ticket1->getId(), $ticketIds);
        $this->assertContains($ticket2->getId(), $ticketIds);
    }

    public function testGetTicketsCanReturnTicketNotInvolvingUserIfAccessIsGiven(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), ['orga:see:tickets:all'], $organization->object());
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
        ]);
        $ticketSearcher->setOrganization($organization->object());

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket->getId(), $tickets[0]->getId());
    }

    public function testGetTicketsTakesCareOfSpecificPermissions(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        // orga:see:tickets:all is applied globally (i.e. to all organizations)
        $this->grantOrga($user->object(), ['orga:see:tickets:all']);
        // but we re-specify a specific role without this permission for this
        // specific organization
        $this->grantOrga($user->object(), ['orga:see'], $organization->object());
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
        ]);
        $ticketSearcher->setOrganization($organization->object());

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(0, count($tickets));
    }

    public function testGetTicketsCanRestrictAccordingToCriteria(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $assignee = UserFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => null,
            'status' => 'new',
        ]);
        $ticket2 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $assignee,
            'status' => 'new',
        ]);
        $ticket2 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => null,
            'status' => 'closed',
        ]);
        $ticketSearcher->setCriteria('assignee', null);
        $ticketSearcher->setCriteria('status', Ticket::OPEN_STATUSES);

        $tickets = $ticketSearcher->getTickets();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket1->getId(), $tickets[0]->getId());
    }

    public function testGetTicketsToAssignReturnsOpenedTicketsWithoutAssignee(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $assignee = UserFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => null,
            'status' => 'new',
        ]);
        $ticket2 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => null,
            'status' => 'closed',
        ]);
        $ticket3 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $assignee,
            'status' => 'new',
        ]);

        $tickets = $ticketSearcher->getTicketsToAssign();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket1->getId(), $tickets[0]->getId());
    }

    public function testCountTicketsToAssignReturnsNumberOfOpenedTicketsWithoutAssignee(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $assignee = UserFactory::createOne();
        TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => null,
            'status' => 'new',
        ]);
        TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => null,
            'status' => 'closed',
        ]);
        TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $assignee,
            'status' => 'new',
        ]);

        $count = $ticketSearcher->countTicketsToAssign();

        $this->assertSame(1, $count);
    }

    public function testGetTicketsOfCurrentUserReturnsOpenedTicketsAssignedToCurrentUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $otherAssignee = UserFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => 'new',
        ]);
        $ticket2 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => 'closed',
        ]);
        $ticket3 = TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $otherAssignee,
            'status' => 'new',
        ]);

        $tickets = $ticketSearcher->getTicketsOfCurrentUser();

        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket1->getId(), $tickets[0]->getId());
    }

    public function testCountTicketsOfCurrentUserReturnsNumberOfOpenedTicketsAssignedToCurrentUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $otherAssignee = UserFactory::createOne();
        TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => 'new',
        ]);
        TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $user,
            'status' => 'closed',
        ]);
        TicketFactory::createOne([
            'createdBy' => $user,
            'assignee' => $otherAssignee,
            'status' => 'new',
        ]);

        $count = $ticketSearcher->countTicketsOfCurrentUser();

        $this->assertSame(1, $count);
    }
}
