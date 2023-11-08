<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service\ContractBilling;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\TimeSpentFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContractBillingTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private ContractBilling $contractBilling;

    /**
     * @before
     */
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var ContractBilling */
        $contractBilling = $container->get(ContractBilling::class);
        $this->contractBilling = $contractBilling;

        $user = UserFactory::createOne();
        $client->loginUser($user->object());
    }

    public function testChargeTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'billingInterval' => 0,
        ])->object();
        $minutes = 20;

        $timeSpent = $this->contractBilling->chargeTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame($minutes, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }

    public function testChargeTimeChargesMaximumOfAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'billingInterval' => 0,
        ])->object();
        $minutes = 70;

        $timeSpent = $this->contractBilling->chargeTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(60, $timeSpent->getTime());
        $this->assertSame(60, $timeSpent->getRealTime());
    }

    public function testChargeTimeChargesConsideringBillingInterval(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'billingInterval' => 30,
        ])->object();
        $minutes = 20;

        $timeSpent = $this->contractBilling->chargeTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(30, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }

    public function testChargeTimeWithBillingIntervalDoesNotChargeMoreThanAvailableTime(): void
    {
        $contract = ContractFactory::createOne([
            'maxHours' => 1,
            'billingInterval' => 30,
        ])->object();
        TimeSpentFactory::createOne([
            'contract' => $contract,
            'time' => 45, // 45 minutes are already deducted from the contract
        ]);
        $minutes = 5;

        $timeSpent = $this->contractBilling->chargeTime($contract, $minutes);

        $this->assertSame($contract->getId(), $timeSpent->getContract()->getId());
        $this->assertSame(15, $timeSpent->getTime());
        $this->assertSame($minutes, $timeSpent->getRealTime());
    }
}
