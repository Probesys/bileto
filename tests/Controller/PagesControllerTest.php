<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

    public function testGetAdvancedSearchSyntaxRendersCorrectlyWithSubjectTickets(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_GET, '/advanced-search-syntax', [
            'subject' => 'tickets',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Quick reference for the tickets advanced search');
    }

    public function testGetAdvancedSearchSyntaxRendersCorrectlyWithSubjectContracts(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->request(Request::METHOD_GET, '/advanced-search-syntax', [
            'subject' => 'contracts',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Quick reference for the contracts advanced search');
    }

    public function testGetAdvancedSearchSyntaxFailsWithOtherSubjects(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/advanced-search-syntax', [
            'subject' => 'foo',
        ]);
    }
}
