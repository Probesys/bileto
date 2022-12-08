<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetShowRendersCorrectly(): void
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
