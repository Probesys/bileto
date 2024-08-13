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
use App\Tests\Factory\LabelFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Utils;
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

    public function testGetTicketsReturnsTicketWithUserAsObserver(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
            'observers' => [$user],
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

    public function testGetTicketsCanReturnTicketsNotInvolvingUserIfAccessIsGiven(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see:tickets:all']);
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $otherUser,
            'assignee' => null,
            'team' => null,
        ]);
        $ticketSearcher->setOrganization($organization->_real());

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanLimitToTicketsNotInvolvingUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see:tickets:all']);
        $userTeam = TeamFactory::createOne([
            'agents' => [$user],
        ]);
        $ticket1 = TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $user,
            'assignee' => null,
            'team' => null,
        ]);
        $ticket2 = TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $otherUser,
            'assignee' => $user,
            'team' => null,
        ]);
        $ticket3 = TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $otherUser,
            'assignee' => null,
            'team' => $userTeam,
        ]);
        $ticket4 = TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $otherUser,
            'assignee' => null,
            'team' => null,
        ]);
        $ticketSearcher->setOrganization($organization->_real());

        $query = Query::fromString('NOT involves:@me');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket4->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanLimitToTicketsNotAssignedToUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = OrganizationFactory::createOne();
        $ticket1 = TicketFactory::createOne([
            'requester' => $user,
            'assignee' => null,
        ]);
        $ticket2 = TicketFactory::createOne([
            'requester' => $user,
            'assignee' => $otherUser,
        ]);
        $ticket3 = TicketFactory::createOne([
            'requester' => $user,
            'assignee' => $user,
        ]);

        $query = Query::fromString('NOT assignee:@me');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(2, $ticketsPagination->count);
        $ids = [
            $ticketsPagination->items[0]->getId(),
            $ticketsPagination->items[1]->getId(),
        ];
        $this->assertContains($ticket1->getId(), $ids);
        $this->assertContains($ticket2->getId(), $ids);
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

    public function testGetTicketsCanRestrictToAGivenLabel(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $label1 = LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $label2 = LabelFactory::createOne([
            'name' => 'Bar',
        ]);
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label1],
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label2],
        ]);

        $query = Query::fromString('label:foo');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanExcludeAGivenLabel(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $label1 = LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $label2 = LabelFactory::createOne([
            'name' => 'Bar',
        ]);
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label1],
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label2],
        ]);

        $query = Query::fromString('-label:foo');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithLabels(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $label = LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label],
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [],
        ]);

        $query = Query::fromString('has:label');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithoutLabels(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var TicketSearcher $ticketSearcher */
        $ticketSearcher = $container->get(TicketSearcher::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $label = LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $ticket1 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label],
        ]);
        $ticket2 = TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [],
        ]);

        $query = Query::fromString('no:label');
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
