<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetShowRendersCorrectlyIfTicketIsCreatedByUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersCorrectlyIfTicketIsRequestedByUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'requester' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersCorrectlyIfTicketIsAssignedToUser(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'assignee' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowRendersCorrectlyIfAccessIsGranted(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:see:tickets:all']);
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "My ticket #{$ticket->getId()}");
    }

    public function testGetShowFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/tickets/{$ticket->getUid()}");
    }

    public function testGetShowRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'title' => 'My ticket',
            'createdBy' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");

        $this->assertResponseRedirects('http://localhost/login', 302);
    }
}
