<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\MessageHandler;

use App\Message;
use App\Tests\Factory;
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

        $this->assertSame(0, Factory\UserFactory::count());

        $bus->dispatch(new Message\SynchronizeLdap());

        $this->assertSame(2, Factory\UserFactory::count());

        $user1 = Factory\UserFactory::first(sortBy: 'email');
        $user2 = Factory\UserFactory::last(sortBy: 'email');
        $this->assertSame('charlie', $user1->getLdapIdentifier());
        $this->assertSame('charlie@example.com', $user1->getEmail());
        $this->assertSame('Charlie Gature', $user1->getName());
        $this->assertSame('dominique', $user2->getLdapIdentifier());
        $this->assertSame('dominique@example.org', $user2->getEmail());
        $this->assertSame('Dominique Aragua', $user2->getName());
    }

    public function testInvokeUpdateUsers(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        $user = Factory\UserFactory::createOne([
            'email' => 'cgature@example.com',
            'name' => 'C. Gature',
            'ldapIdentifier' => 'charlie',
        ]);

        $bus->dispatch(new Message\SynchronizeLdap());

        $user->_refresh();
        $this->assertSame('charlie@example.com', $user->getEmail());
        $this->assertSame('Charlie Gature', $user->getName());
        $this->assertSame('charlie', $user->getLdapIdentifier());
    }

    public function testInvokeCanSetDefaultAuthorizations(): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface */
        $bus = $container->get(MessageBusInterface::class);
        $defaultRole = Factory\RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);
        $defaultOrganization = Factory\OrganizationFactory::createOne([
            'domains' => ['example.com'], // don't include example.org
        ]);

        $bus->dispatch(new Message\SynchronizeLdap());

        $this->assertSame(2, Factory\UserFactory::count());
        $this->assertSame(1, Factory\AuthorizationFactory::count());

        $users = Factory\UserFactory::all();
        $this->assertSame('charlie@example.com', $users[0]->getEmail());
        Factory\AuthorizationFactory::assert()->exists([
            'holder' => $users[0],
            'role' => $defaultRole,
            'organization' => $defaultOrganization,
        ]);
        $this->assertSame('dominique@example.org', $users[1]->getEmail());
        // example.org is not handled by any organization
        Factory\AuthorizationFactory::assert()->notExists([
            'holder' => $users[1],
        ]);
    }
}
