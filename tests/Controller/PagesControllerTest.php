<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
        $client->loginUser($user->object());
        $organization1 = OrganizationFactory::createOne([
            'name' => 'Orga 1',
        ]);
        $organization2 = OrganizationFactory::createOne([
            'name' => 'Orga 2',
        ]);
        $subOrganization = OrganizationFactory::createOne([
            'name' => 'Sub-orga',
            'parentsPath' => "/{$organization1->getId()}/",
        ]);
        $this->grantOrga($user->object(), ['orga:see'], $organization1->object());

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to Bileto');
        $this->assertSelectorTextContains(
            '[data-test="organization-item"]:nth-child(1)',
            'Orga 1',
        );
        $this->assertSelectorNotExists(
            '[data-test="organization-item"]:nth-child(2)',
        );
        $this->assertSelectorTextContains(
            '[data-test="organization-item"] [data-test="organization-item"]',
            'Sub-orga',
        );
    }

    public function testGetHomeListsAllOrganizationsIfGlobalAccess(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization1 = OrganizationFactory::createOne([
            'name' => 'Orga 1',
        ]);
        $organization2 = OrganizationFactory::createOne([
            'name' => 'Orga 2',
        ]);
        $subOrganization = OrganizationFactory::createOne([
            'name' => 'Sub-orga',
            'parentsPath' => "/{$organization1->getId()}/",
        ]);
        $this->grantOrga($user->object(), ['orga:see'], null);

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '[data-test="organization-item"]:nth-child(1)',
            'Orga 1',
        );
        $this->assertSelectorTextContains(
            '[data-test="organization-item"]:nth-child(2)',
            'Orga 2',
        );
        $this->assertSelectorTextContains(
            '[data-test="organization-item"] [data-test="organization-item"]',
            'Sub-orga',
        );
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

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'About Bileto');
    }

    public function testGetAdvancedSearchSyntaxRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('GET', '/advanced-search-syntax');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Quick reference for the advanced syntax');
    }
}
