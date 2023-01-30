<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PagesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetHomeRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('GET', '/');

        $this->assertSelectorTextContains('h1', 'Welcome to Bileto');
    }

    public function testGetHomeRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testGetAboutRendersCorrectly(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/about');

        $this->assertSelectorTextContains('h1', 'About Bileto');
    }
}
