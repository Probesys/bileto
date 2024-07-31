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
use App\Tests\Factory\TeamFactory;
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
        $client->loginUser($user->_real());
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
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
            'requester' => $user,
        ]);

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsReturnsTicketAssignedToUserTeam(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne([
            'agents' => [$user],
        ]);
        $ticket = TicketFactory::createOne([
            'team' => $team,
        ]);

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsReturnsTicketWithDistinct(): void
    {
        // In the case of a ticket assigned to a team AND an agent of this
        // team, AND that the team has several agents, AND that there are at
        // least as many tickets as the maxResults option, some tickets may be
        // missing without the SQL "distinct" clause.

        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        list($user, $otherUser) = UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne([
            'agents' => [$user, $otherUser],
        ]);
        $organization = OrganizationFactory::createOne();
        $this->grantTeam($team->_real(), ['orga:see'], $organization->_real());
        TicketFactory::createOne([
            'organization' => $organization,
            'team' => $team,
            'assignee' => $user,
        ]);
        TicketFactory::createOne([
            'organization' => $organization,
            'team' => $team,
            'assignee' => $user,
        ]);

        $ticketsPagination = $ticketSearcher->getTickets(paginationOptions: [
            'page' => 0,
            'maxResults' => 2,
        ]);

        $this->assertSame(2, $ticketsPagination->count);
    }

    public function testGetTicketsDoesNotReturnTicketNotInvolvingUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
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
        $client->loginUser($user->_real());
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
        $ticketSearcher->setOrganization($organization1->_real());

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
        $client->loginUser($user->_real());
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
            $organization1->_real(),
            $organization2->_real(),
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
        $client->loginUser($user->_real());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see:tickets:all'], $organization->_real());
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
        ]);
        $ticketSearcher->setOrganization($organization->_real());

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
        $client->loginUser($user->_real());
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

    public function testGetTicketsCanExcludeAGivenContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
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

        $query = Query::fromString('NOT contract:#' . $contract1->getId());
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithAContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $contract = ContractFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract],
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [],
        ]);

        $query = Query::fromString('has:contract');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithoutAContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $contract = ContractFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract],
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [],
        ]);

        $query = Query::fromString('no:contract');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsReturnsTicketMatchingAQuery(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
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
        $client->loginUser($user->_real());
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
        $client->loginUser($user->_real());
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

    public function testCountTicketsReturnsNumberOfTicketsWithDistinct(): void
    {
        // In the case of a ticket assigned to a team AND an agent of this
        // team, AND that the team has several agents, the count may be wrong
        // without the SQL "distinct" clause.

        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        list($user, $otherUser) = UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $team = TeamFactory::createOne([
            'agents' => [$user, $otherUser],
        ]);
        $organization = OrganizationFactory::createOne();
        $this->grantTeam($team->_real(), ['orga:see'], $organization->_real());
        TicketFactory::createOne([
            'organization' => $organization,
            'team' => $team,
            'assignee' => $user,
        ]);

        $count = $ticketSearcher->countTickets();

        $this->assertSame(1, $count);
    }
}
