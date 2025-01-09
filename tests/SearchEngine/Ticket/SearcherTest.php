<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine\Ticket;

use App\SearchEngine;
use App\Tests;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SearcherTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;

    public function testGetTicketsReturnsTicketAssignedToUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $team = Factory\TeamFactory::createOne([
            'agents' => [$user],
        ]);
        $ticket = Factory\TicketFactory::createOne([
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne();

        $ticketsPagination = $ticketSearcher->getTickets();

        $this->assertSame(0, $ticketsPagination->count);
    }

    public function testGetTicketsCanRestrictToAGivenOrganization(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization1 = Factory\OrganizationFactory::createOne();
        $organization2 = Factory\OrganizationFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'organization' => $organization1,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization1 = Factory\OrganizationFactory::createOne();
        $organization2 = Factory\OrganizationFactory::createOne();
        $organization3 = Factory\OrganizationFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'organization' => $organization1,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'organization' => $organization2,
        ]);
        $ticket3 = Factory\TicketFactory::createOne([
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see:tickets:all']);
        $ticket = Factory\TicketFactory::createOne([
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see:tickets:all']);
        $userTeam = Factory\TeamFactory::createOne([
            'agents' => [$user],
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $user,
            'assignee' => null,
            'team' => null,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $otherUser,
            'assignee' => $user,
            'team' => null,
        ]);
        $ticket3 = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $otherUser,
            'assignee' => null,
            'team' => $userTeam,
        ]);
        $ticket4 = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'requester' => $otherUser,
            'assignee' => null,
            'team' => null,
        ]);
        $ticketSearcher->setOrganization($organization->_real());

        $query = SearchEngine\Query::fromString('NOT involves:@me');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket4->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanLimitToTicketsNotAssignedToUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'assignee' => null,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'assignee' => $otherUser,
        ]);
        $ticket3 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'assignee' => $user,
        ]);

        $query = SearchEngine\Query::fromString('NOT assignee:@me');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(2, $ticketsPagination->count);
        $ids = [
            $ticketsPagination->items[0]->getId(),
            $ticketsPagination->items[1]->getId(),
        ];
        $this->assertContains($ticket1->getId(), $ids);
        $this->assertContains($ticket2->getId(), $ids);
    }

    public function testGetTicketsCanLimitToTicketsObservedByUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'observers' => [],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'observers' => [$user],
        ]);

        $query = SearchEngine\Query::fromString('observer:@me');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanLimitToTicketsAssignedToSpecificTeam(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team1 = Factory\TeamFactory::createOne([
            'name' => 'Team support',
        ]);
        $team2 = Factory\TeamFactory::createOne([
            'name' => 'Team Web',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'team' => $team1,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'team' => $team2,
        ]);

        $query = SearchEngine\Query::fromString('team:web');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanExcludeTicketsAssignedToSpecificTeam(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team1 = Factory\TeamFactory::createOne([
            'name' => 'Team support',
        ]);
        $team2 = Factory\TeamFactory::createOne([
            'name' => 'Team Web',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'team' => $team1,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'team' => $team2,
        ]);

        $query = SearchEngine\Query::fromString('-team:web');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanLimitToTicketsAssignedToAnyTeam(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team = Factory\TeamFactory::createOne([
            'name' => 'Team support',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'requester' => $user,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'team' => $team,
        ]);

        $query = SearchEngine\Query::fromString('has:team');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanExcludeTicketsAssignedToAnyTeam(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $team = Factory\TeamFactory::createOne([
            'name' => 'Team support',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'requester' => $user,
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'requester' => $user,
            'team' => $team,
        ]);

        $query = SearchEngine\Query::fromString('no:team');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanRestrictToAGivenContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $contract1 = Factory\ContractFactory::createOne();
        $contract2 = Factory\ContractFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract1],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract2],
        ]);

        $query = SearchEngine\Query::fromString('contract:#' . $contract1->getId());
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanExcludeAGivenContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $contract1 = Factory\ContractFactory::createOne();
        $contract2 = Factory\ContractFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract1],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract2],
        ]);

        $query = SearchEngine\Query::fromString('NOT contract:#' . $contract1->getId());
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithAContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $contract = Factory\ContractFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [],
        ]);

        $query = SearchEngine\Query::fromString('has:contract');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithoutAContract(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $contract = Factory\ContractFactory::createOne();
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [$contract],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'contracts' => [],
        ]);

        $query = SearchEngine\Query::fromString('no:contract');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanRestrictToAGivenLabel(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $label1 = Factory\LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $label2 = Factory\LabelFactory::createOne([
            'name' => 'Bar',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label1],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label2],
        ]);

        $query = SearchEngine\Query::fromString('label:foo');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanExcludeAGivenLabel(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $label1 = Factory\LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $label2 = Factory\LabelFactory::createOne([
            'name' => 'Bar',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label1],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label2],
        ]);

        $query = SearchEngine\Query::fromString('-label:foo');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithLabels(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $label = Factory\LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [],
        ]);

        $query = SearchEngine\Query::fromString('has:label');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsCanGetTicketsWithoutLabels(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $label = Factory\LabelFactory::createOne([
            'name' => 'Foo',
        ]);
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [$label],
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'labels' => [],
        ]);

        $query = SearchEngine\Query::fromString('no:label');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket2->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testGetTicketsReturnsTicketMatchingAQuery(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket1 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'new',
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'closed',
        ]);

        $query = SearchEngine\Query::fromString('status:new');
        $ticketsPagination = $ticketSearcher->getTickets($query);

        $this->assertSame(1, $ticketsPagination->count);
        $this->assertSame($ticket1->getId(), $ticketsPagination->items[0]->getId());
    }

    public function testCountTicketsReturnsNumberOfTickets(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        Factory\TicketFactory::createOne([
            'assignee' => $user,
        ]);

        $count = $ticketSearcher->countTickets();

        $this->assertSame(1, $count);
    }

    public function testCountTicketsReturnsNumberOfTicketsAccordingToAQuery(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        Factory\TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'new',
        ]);
        Factory\TicketFactory::createOne([
            'assignee' => $user,
            'status' => 'closed',
        ]);

        $query = SearchEngine\Query::fromString('status:new');
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
        /** @var SearchEngine\Ticket\Searcher */
        $ticketSearcher = $container->get(SearchEngine\Ticket\Searcher::class);
        list($user, $otherUser) = Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $team = Factory\TeamFactory::createOne([
            'agents' => [$user, $otherUser],
        ]);
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantTeam($team->_real(), ['orga:see'], $organization->_real());
        Factory\TicketFactory::createOne([
            'organization' => $organization,
            'team' => $team,
            'assignee' => $user,
        ]);

        $count = $ticketSearcher->countTickets();

        $this->assertSame(1, $count);
    }
}
