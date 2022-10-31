<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity\Organization;
use App\Factory\OrganizationFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Tickets');
    }

    public function testGetIndexRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/tickets");

        $this->assertResponseRedirects('http://localhost/login', 302);
    }
}
