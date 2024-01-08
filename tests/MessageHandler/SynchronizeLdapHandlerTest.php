<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\MessageHandler;

use App\Message\SynchronizeLdap;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SynchronizeLdapHandlerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testInvokeCreatesUsers(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);

        $this->assertSame(0, UserFactory::count());

        $bus->dispatch(new SynchronizeLdap());

        $this->assertSame(2, UserFactory::count());

        $users = UserFactory::all();
        $this->assertSame('charlie', $users[0]->getLdapIdentifier());
        $this->assertSame('charlie@example.com', $users[0]->getEmail());
        $this->assertSame('Charlie Gature', $users[0]->getName());
        $this->assertSame('dominique', $users[1]->getLdapIdentifier());
        $this->assertSame('dominique@example.com', $users[1]->getEmail());
        $this->assertSame('Dominique Aragua', $users[1]->getName());
    }

    public function testInvokeUpdateUsers(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        $user = UserFactory::createOne([
            'email' => 'cgature@example.com',
            'name' => 'C. Gature',
            'ldapIdentifier' => 'charlie',
        ]);

        $bus->dispatch(new SynchronizeLdap());

        $user->refresh();
        $this->assertSame('charlie@example.com', $user->getEmail());
        $this->assertSame('Charlie Gature', $user->getName());
        $this->assertSame('charlie', $user->getLdapIdentifier());
    }
}
