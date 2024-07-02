<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PagesControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetHomeRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_GET, '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to Bileto');
    }

    public function testGetHomeRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $crawler = $client->request(Request::METHOD_GET, '/');

        $this->assertResponseRedirects('/login', 302);
    }

    public function testGetAboutRendersCorrectly(): void
    {
        $client = static::createClient();

        $crawler = $client->request(Request::METHOD_GET, '/about');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'About Bileto');
    }

    public function testGetAdvancedSearchSyntaxRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_GET, '/advanced-search-syntax');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Quick reference for the advanced syntax');
    }
}
