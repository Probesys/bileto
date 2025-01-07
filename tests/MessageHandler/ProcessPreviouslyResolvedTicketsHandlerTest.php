<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\MessageHandler;

use App\Message;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProcessPreviouslyResolvedTicketsHandlerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testInvokeClosesTicketsResolvedMoreThanOneWeekAgo(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $ticket1 = Factory\TicketFactory::createOne([
            'status' => 'resolved',
            'statusChangedAt' => Utils\Time::ago(7, 'days'),
        ]);
        $ticket2 = Factory\TicketFactory::createOne([
            'status' => 'resolved',
            'statusChangedAt' => Utils\Time::ago(6, 'days'),
        ]);
        $ticket3 = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'statusChangedAt' => Utils\Time::ago(7, 'days'),
        ]);

        $this->assertSame(3, Factory\TicketFactory::count());

        $bus->dispatch(new Message\ProcessPreviouslyResolvedTickets());

        $ticket1->_refresh();
        $this->assertSame('closed', $ticket1->getStatus());
        $ticket2->_refresh();
        $this->assertSame('resolved', $ticket2->getStatus());
        $ticket3->_refresh();
        $this->assertSame('in_progress', $ticket3->getStatus());
    }
}
