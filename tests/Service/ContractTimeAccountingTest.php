<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service\ContractTimeAccounting;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\TimeSpentFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContractTimeAccountingTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private ContractTimeAccounting $contractTimeAccounting;

    #[Before]
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var ContractTimeAccounting */
        $contractTimeAccounting = $container->get(ContractTimeAccounting::class);
        $this->contractTimeAccounting = $contractTimeAccounting;

        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
    }

    public function testAccountTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->_real();
        $minutes = 20;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame($minutes, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
        $contractTimeSpents = $contract->getTimeSpents();
        $this->assertSame(1, count($contractTimeSpents));
    }

    public function testAccountTimeAccountsMaximumOfAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->_real();
        $minutes = 70;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(60, $timeSpent->getTime());
        $this->assertSame(60, $timeSpent->getRealTime());
        $contractTimeSpents = $contract->getTimeSpents();
        $this->assertSame(1, count($contractTimeSpents));
    }

    public function testAccountTimeAccountsConsideringTimeAccountingUnit(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->_real();
        $minutes = 20;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(30, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
        $contractTimeSpents = $contract->getTimeSpents();
        $this->assertSame(1, count($contractTimeSpents));
    }

    public function testAccountTimeWithTimeAccountingUnitDoesNotAccountMoreThanAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->_real();
        TimeSpentFactory::createOne([
            'contract' => $contract,
            'time' => 45, // 45 minutes are already deducted from the contract
        ]);
        $minutes = 5;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(15, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
        $contractTimeSpents = $contract->getTimeSpents();
        $this->assertSame(2, count($contractTimeSpents));
    }

    public function testAccountTimeSpents(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->_real();
        $minutes = 20;
        $timeSpent = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => $minutes,
        ])->_real();

        $this->contractTimeAccounting->accountTimeSpents($contract, [$timeSpent]);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame($minutes, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
        $contractTimeSpents = $contract->getTimeSpents();
        $this->assertSame(1, count($contractTimeSpents));
    }

    public function testAccountTimeSpentsDoNotAccountMoreThanAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->_real();
        $timeSpent1 = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => 20,
        ])->_real();
        $timeSpent2 = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => 50,
        ])->_real();
        $timeSpent3 = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => 20,
        ])->_real();

        $this->contractTimeAccounting->accountTimeSpents($contract, [$timeSpent1, $timeSpent2, $timeSpent3]);

        $this->assertSame($contract->getId(), $timeSpent1->getContract()->getId());
        $this->assertSame(20, $timeSpent1->getTime());
        $this->assertSame(20, $timeSpent1->getRealTime());
        $this->assertNull($timeSpent2->getContract());
        // Even if this TimeSpent could have been associated to the contract,
        // the ContractTimeAccounting service stopped at the first TimeSpent
        // overflowing the available time.
        $this->assertNull($timeSpent3->getContract());
        $contractTimeSpents = $contract->getTimeSpents();
        $this->assertSame(1, count($contractTimeSpents));
        $this->assertSame($timeSpent1->getId(), $contractTimeSpents[0]->getId());
    }

    public function testAccountTimeSpentsAccountsConsideringTimeAccountingUnit(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->_real();
        $minutes = 20;
        $timeSpent = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => $minutes,
        ])->_real();

        $this->contractTimeAccounting->accountTimeSpents($contract, [$timeSpent]);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(30, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
        $contractTimeSpents = $contract->getTimeSpents();
        $this->assertSame(1, count($contractTimeSpents));
    }

    public function testUnaccountTimeSpents(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->_real();
        $timeSpent = TimeSpentFactory::createOne([
            'contract' => $contract,
            'realTime' => 20,
            'time' => 30,
        ])->_real();

        $this->contractTimeAccounting->unaccountTimeSpents([$timeSpent]);

        $this->assertNull($timeSpent->getContract());
        $this->assertSame(20, $timeSpent->getTime());
        $this->assertSame(20, $timeSpent->getRealTime());
        $this->assertEmpty($contract->getTimeSpents());
    }

    public function testUnaccountTimeSpentsWithTimeSpentWithoutContract(): void
    {
        $timeSpent = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => 20,
            'time' => 20,
        ])->_real();

        $this->contractTimeAccounting->unaccountTimeSpents([$timeSpent]);

        $this->assertNull($timeSpent->getContract());
        $this->assertSame(20, $timeSpent->getTime());
        $this->assertSame(20, $timeSpent->getRealTime());
    }

    public function testReaccountTimeSpents(): void
    {
        $initialContract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->_real();
        $newContract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 15,
        ])->_real();
        $timeSpent = TimeSpentFactory::createOne([
            'contract' => $initialContract,
            'realTime' => 10,
            'time' => 30,
        ])->_real();

        $this->contractTimeAccounting->reaccountTimeSpents($newContract, [$timeSpent]);

        $this->assertSame($newContract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(15, $timeSpent->getTime());
        $this->assertSame(10, $timeSpent->getRealTime());
        $contractTimeSpents = $initialContract->getTimeSpents();
        $this->assertSame(0, count($contractTimeSpents));
        $contractTimeSpents = $newContract->getTimeSpents();
        $this->assertSame(1, count($contractTimeSpents));
    }
}
