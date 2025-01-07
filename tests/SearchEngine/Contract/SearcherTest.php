<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\SearchEngine\Contract;

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

    public function testGetContractsReturnsContractsAccessibleToUser(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization1 = Factory\OrganizationFactory::createOne()->_real();
        $organization2 = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see'], $organization1);
        $this->grantOrga($user, ['orga:see', 'orga:see:contracts'], $organization2);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization1,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization2,
        ]);

        $contractsPagination = $contractSearcher->getContracts();

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanRestrictToAGivenOrganization(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization1 = Factory\OrganizationFactory::createOne()->_real();
        $organization2 = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization1);
        $this->grantOrga($user, ['orga:see:contracts'], $organization2);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization1,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization2,
        ]);
        $contractSearcher->setOrganization($organization2);

        $contractsPagination = $contractSearcher->getContracts();

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsReturnsNothingIfUserHasNotPermission(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract = Factory\ContractFactory::createOne([
            'organization' => $organization,
        ]);
        $contractSearcher->setOrganization($organization);

        $contractsPagination = $contractSearcher->getContracts();

        $this->assertSame(0, $contractsPagination->count);
    }

    public function testGetContractsCanReturnContractsById(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
        ]);

        $query = SearchEngine\Query::fromString("#{$contract2->getId()}");
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByName(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'name' => 'Foo',
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'name' => 'Bar',
        ]);

        $query = SearchEngine\Query::fromString('bar');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByOrganizationName(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization1 = Factory\OrganizationFactory::createOne([
            'name' => 'Foo',
        ])->_real();
        $organization2 = Factory\OrganizationFactory::createOne([
            'name' => 'Bar',
        ])->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization1);
        $this->grantOrga($user, ['orga:see:contracts'], $organization2);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization1,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization2,
        ]);

        $query = SearchEngine\Query::fromString('org:bar');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByStatusComing(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $twoWeeksAgo = Utils\Time::ago(2, 'weeks');
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekFromNow,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $twoWeeksAgo,
            'endAt' => $oneWeekAgo,
            'maxHours' => 10,
        ]);

        $query = SearchEngine\Query::fromString('status:coming');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByStatusNotComing(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $twoWeeksAgo = Utils\Time::ago(2, 'weeks');
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekFromNow,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $twoWeeksAgo,
            'endAt' => $oneWeekAgo,
            'maxHours' => 10,
        ]);

        $query = SearchEngine\Query::fromString('-status:coming');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(2, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
        $this->assertSame($contract3->getId(), $contractsPagination->items[1]->getId());
    }

    public function testGetContractsCanReturnContractsByStatusOngoing(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $twoWeeksAgo = Utils\Time::ago(2, 'weeks');
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekFromNow,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $twoWeeksAgo,
            'endAt' => $oneWeekAgo,
            'maxHours' => 10,
        ]);

        $query = SearchEngine\Query::fromString('status:ongoing');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByStatusNotOngoing(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $twoWeeksAgo = Utils\Time::ago(2, 'weeks');
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekFromNow,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $twoWeeksAgo,
            'endAt' => $oneWeekAgo,
            'maxHours' => 10,
        ]);

        $query = SearchEngine\Query::fromString('-status:ongoing');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(2, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
        $this->assertSame($contract3->getId(), $contractsPagination->items[1]->getId());
    }

    public function testGetContractsCanReturnContractsByStatusFinished(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $twoWeeksAgo = Utils\Time::ago(2, 'weeks');
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekFromNow,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $twoWeeksAgo,
            'endAt' => $oneWeekAgo,
            'maxHours' => 10,
        ]);

        $query = SearchEngine\Query::fromString('status:finished');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract3->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByStatusNotFinished(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $twoWeeksAgo = Utils\Time::ago(2, 'weeks');
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekFromNow,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $twoWeeksAgo,
            'endAt' => $oneWeekAgo,
            'maxHours' => 10,
        ]);

        $query = SearchEngine\Query::fromString('-status:finished');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(2, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
        $this->assertSame($contract2->getId(), $contractsPagination->items[1]->getId());
    }

    public function testGetContractsCanReturnContractsByStatusComingOrOngoing(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $twoWeeksAgo = Utils\Time::ago(2, 'weeks');
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekFromNow,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $twoWeeksAgo,
            'endAt' => $oneWeekAgo,
            'maxHours' => 10,
        ]);

        $query = SearchEngine\Query::fromString('status:coming,ongoing');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(2, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
        $this->assertSame($contract2->getId(), $contractsPagination->items[1]->getId());
    }

    public function testGetContractsCanReturnContractsByAlertTime(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 0,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 0,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract1,
            'time' => 7 * 60,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract2,
            'time' => 8 * 60,
        ]);

        $query = SearchEngine\Query::fromString('alert:time');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByAlertTimeWithDecimal(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 1,
            'hoursAlert' => 50, // The alert is set to 30 minutes
            'dateAlert' => 0,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 1,
            'hoursAlert' => 50, // The alert is set to 30 minutes
            'dateAlert' => 0,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract2,
            'time' => 30,
        ]);

        $query = SearchEngine\Query::fromString('alert:time');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByNotAlertTime(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 0,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 0,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract1,
            'time' => 7 * 60,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract2,
            'time' => 8 * 60,
        ]);

        $query = SearchEngine\Query::fromString('-alert:time');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByAlertDate(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 0,
            'dateAlert' => 8,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 0,
            'dateAlert' => 8,
        ]);

        $query = SearchEngine\Query::fromString('alert:date');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByNotAlertDate(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 0,
            'dateAlert' => 8,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 0,
            'dateAlert' => 8,
        ]);

        $query = SearchEngine\Query::fromString('-alert:date');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByAlertTimeOrDate(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract1,
            'time' => 8 * 60,
        ]);

        $query = SearchEngine\Query::fromString('alert:time,date');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(2, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
        $this->assertSame($contract2->getId(), $contractsPagination->items[1]->getId());
    }

    public function testGetContractsCanReturnContractsByHasAlert(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract1,
            'time' => 8 * 60,
        ]);

        $query = SearchEngine\Query::fromString('has:alert');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(2, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
        $this->assertSame($contract2->getId(), $contractsPagination->items[1]->getId());
    }

    public function testGetContractsCanReturnContractsByNotHasAlert(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract1,
            'time' => 8 * 60,
        ]);

        $query = SearchEngine\Query::fromString('-has:alert');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract3->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByNoAlert(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract1,
            'time' => 8 * 60,
        ]);

        $query = SearchEngine\Query::fromString('no:alert');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract3->getId(), $contractsPagination->items[0]->getId());
    }

    public function testGetContractsCanReturnContractsByNotNoAlert(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $oneWeekAgo = Utils\Time::ago(1, 'week');
        $oneWeekFromNow = Utils\Time::fromNow(1, 'week');
        $twoWeeksFromNow = Utils\Time::fromNow(2, 'weeks');
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $oneWeekFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $oneWeekAgo,
            'endAt' => $twoWeeksFromNow,
            'maxHours' => 10,
            'hoursAlert' => 80,
            'dateAlert' => 8,
        ]);
        Factory\TimeSpentFactory::createOne([
            'contract' => $contract1,
            'time' => 8 * 60,
        ]);

        $query = SearchEngine\Query::fromString('-no:alert');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(2, $contractsPagination->count);
        $this->assertSame($contract1->getId(), $contractsPagination->items[0]->getId());
        $this->assertSame($contract2->getId(), $contractsPagination->items[1]->getId());
    }

    public function testGetContractsCanReturnContractsByRenewalStatus(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'renewedBy' => null,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'renewedBy' => Factory\ContractFactory::createOne(),
        ]);

        $query = SearchEngine\Query::fromString('is:renewed');
        $contractsPagination = $contractSearcher->getContracts($query);

        $this->assertSame(1, $contractsPagination->count);
        $this->assertSame($contract2->getId(), $contractsPagination->items[0]->getId());
    }

    public function testCountContractsReturnsNumberOfContracts(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $user = Factory\UserFactory::createOne()->_real();
        $client->loginUser($user);
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantOrga($user, ['orga:see:contracts'], $organization);
        $contractSearcher = $container->get(SearchEngine\Contract\Searcher::class);
        $contract1 = Factory\ContractFactory::createOne([
            'organization' => $organization,
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'organization' => $organization,
        ]);

        $query = SearchEngine\Query::fromString("#{$contract2->getId()}");
        $count = $contractSearcher->countContracts($query);

        $this->assertSame(1, $count);
    }
}
