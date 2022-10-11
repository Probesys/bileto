<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HomeControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testShowRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('p', 'Hello Bileto!');
    }

    public function testShowRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        $this->assertResponseRedirects('http://localhost/login', 302);
    }
}
