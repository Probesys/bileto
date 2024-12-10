<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Entity;

use App\Entity\Ticket;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
//use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetSumTimeSpentWithAccountedType(): void
    {
        $contract = Factory\ContractFactory::createOne([
            'timeAccountingUnit' => 30,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'contracts' => [$contract],
        ]);
        $accountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 30,
            'contract' => $contract,
        ]);
        $accountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 30,
            'contract' => $contract,
        ]);
        $unaccountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 15,
            'contract' => null,
        ]);
        $unaccountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 5,
            'contract' => null,
        ]);

        $time = $ticket->getSumTimeSpent('accounted');

        $this->assertSame(60, $time);
    }

    public function testGetSumTimeSpentWithUnaccountedType(): void
    {
        $contract = Factory\ContractFactory::createOne([
            'timeAccountingUnit' => 30,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'contracts' => [$contract],
        ]);
        $accountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 30,
            'contract' => $contract,
        ]);
        $accountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 30,
            'contract' => $contract,
        ]);
        $unaccountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 15,
            'contract' => null,
        ]);
        $unaccountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 5,
            'contract' => null,
        ]);

        $time = $ticket->getSumTimeSpent('unaccounted');

        $this->assertSame(20, $time);
    }

    public function testGetSumTimeSpentWithRealType(): void
    {
        $contract = Factory\ContractFactory::createOne([
            'timeAccountingUnit' => 30,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'contracts' => [$contract],
        ]);
        $accountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 30,
            'contract' => $contract,
        ]);
        $accountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 30,
            'contract' => $contract,
        ]);
        $unaccountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 15,
            'contract' => null,
        ]);
        $unaccountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 5,
            'contract' => null,
        ]);

        $time = $ticket->getSumTimeSpent('real');

        $this->assertSame(40, $time);
    }

    public function testGetSumTimeSpentWithAnotherType(): void
    {
        $contract = Factory\ContractFactory::createOne([
            'timeAccountingUnit' => 30,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'contracts' => [$contract],
        ]);
        $accountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 30,
            'contract' => $contract,
        ]);
        $accountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 30,
            'contract' => $contract,
        ]);
        $unaccountedTimeSpent1 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 15,
            'time' => 15,
            'contract' => null,
        ]);
        $unaccountedTimeSpent2 = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => 5,
            'time' => 5,
            'contract' => null,
        ]);

        $this->expectException(\DomainException::class);

        /** @phpstan-ignore argument.type */
        $ticket->getSumTimeSpent('invalid value');
    }
}
