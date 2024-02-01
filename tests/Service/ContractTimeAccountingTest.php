<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service\ContractTimeAccounting;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\TimeSpentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContractTimeAccountingTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private ContractTimeAccounting $contractTimeAccounting;

    /**
     * @before
     */
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var ContractTimeAccounting */
        $contractTimeAccounting = $container->get(ContractTimeAccounting::class);
        $this->contractTimeAccounting = $contractTimeAccounting;

        $user = UserFactory::createOne();
        $client->loginUser($user->object());
    }

    public function testAccountTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->object();
        $minutes = 20;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame($minutes, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }

    public function testAccountTimeAccountsMaximumOfAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->object();
        $minutes = 70;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(60, $timeSpent->getTime());
        $this->assertSame(60, $timeSpent->getRealTime());
    }

    public function testAccountTimeAccountsConsideringTimeAccountingUnit(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->object();
        $minutes = 20;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(30, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }

    public function testAccountTimeWithTimeAccountingUnitDoesNotAccountMoreThanAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->object();
        TimeSpentFactory::createOne([
            'contract' => $contract,
            'time' => 45, // 45 minutes are already deducted from the contract
        ]);
        $minutes = 5;

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(15, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }

    public function testAccountTimeSpents(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->object();
        $minutes = 20;
        $timeSpent = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => $minutes,
        ])->object();

        $this->contractTimeAccounting->accountTimeSpents($contract, [$timeSpent]);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame($minutes, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }

    public function testAccountTimeSpentsDoNotAccountMoreThanAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 0,
        ])->object();
        $timeSpent1 = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => 20,
        ])->object();
        $timeSpent2 = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => 50,
        ])->object();
        $timeSpent3 = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => 20,
        ])->object();

        $this->contractTimeAccounting->accountTimeSpents($contract, [$timeSpent1, $timeSpent2, $timeSpent3]);

        $this->assertSame($contract->getId(), $timeSpent1->getContract()->getId());
        $this->assertSame(20, $timeSpent1->getTime());
        $this->assertSame(20, $timeSpent1->getRealTime());
        $this->assertNull($timeSpent2->getContract());
        // Even if this TimeSpent could have been associated to the contract,
        // the ContractTimeAccounting service stopped at the first TimeSpent
        // overflowing the available time.
        $this->assertNull($timeSpent3->getContract());
    }

    public function testAccountTimeSpentsAccountsConsideringTimeAccountingUnit(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'timeAccountingUnit' => 30,
        ])->object();
        $minutes = 20;
        $timeSpent = TimeSpentFactory::createOne([
            'contract' => null,
            'realTime' => $minutes,
        ])->object();

        $this->contractTimeAccounting->accountTimeSpents($contract, [$timeSpent]);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(30, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }
}
