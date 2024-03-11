<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine;

use App\Entity\Ticket;
use App\SearchEngine\Query;
use App\SearchEngine\TicketSearcher;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
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

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket->getId(), $ticketsPagination->items[0]->getId());
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

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket->getId(), $ticketsPagination->items[0]->getId());
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

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(0, $ticketsPagination->count);
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
            'assignee' => $user,
            'organization' => $organization1,
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'organization' => $organization2,
        ]);
        $ticketSearcher->setOrganization($organization1->object());

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
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
            'assignee' => $user,
            'organization' => $organization1,
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'organization' => $organization2,
        ]);
        $ticket3 = TicketFactory::createOne([
            'assignee' => $user,
            'organization' => $organization3,
        ]);
        $ticketSearcher->setOrganizations([
            $organization1->object(),
            $organization2->object(),
        ]);

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(2, $ticketsPagination->count);
        $ticketIds = array_map(function ($ticket): int {
            return $ticket->getId();
        }, $ticketsPagination->items);
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

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanRestrictToAGivenContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $contract1 = ContractFactory::createOne();
        $contract2 = ContractFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract1],
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract2],
        ]);

        $query = Query::fromString('contract:#' . $contract1->getId());
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsReturnsTicketMatchingAQuery(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'new',
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'closed',
        ]);

        $query = Query::fromString('status:new');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testCountTicketsReturnsNumberOfTickets(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        TicketFactory::createOne([
            'assignee' => $user,
        ]);

        $count = $ticketSearcher->countTickets();

        $this->assertSame(1, $count);
    }

    public function testCountTicketsReturnsNumberOfTicketsAccordingToAQuery(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'new',
        ]);
        TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'closed',
        ]);

        $query = Query::fromString('status:new');
        $count = $ticketSearcher->countTickets($query);

        $this->assertSame(1, $count);
    }
}
