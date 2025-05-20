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

class CleanDataHandlerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testInvokeDeletesExpiredTokens(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $tokenExpired = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::ago(1, 'hour'),
        ]);
        $tokenNotExpired = Factory\TokenFactory::createOne([
            'expiredAt' => Utils\Time::fromNow(1, 'hour'),
        ]);

        $this->assertSame(2, Factory\TokenFactory::count());

        $bus->dispatch(new Message\CleanData());

        $this->assertSame(1, Factory\TokenFactory::count());

        $token = Factory\TokenFactory::last();
        $this->assertSame($tokenNotExpired->getId(), $token->getId());
    }

    public function testInvokeDeletesSessionLogsOlderThanOneYear(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $sessionLogExpired = Factory\SessionLogFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'year'),
        ]);
        $sessionLogNotExpired = Factory\SessionLogFactory::createOne([
            'createdAt' => Utils\Time::ago(6, 'months'),
        ]);

        $this->assertSame(2, Factory\SessionLogFactory::count());

        $bus->dispatch(new Message\CleanData());

        $this->assertSame(1, Factory\SessionLogFactory::count());

        $sessionLog = Factory\SessionLogFactory::last();
        $this->assertSame($sessionLogNotExpired->getId(), $sessionLog->getId());
    }
}
