<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Organization;
use App\Factory\OrganizationFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class OrganizationsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetIndexListsOrganizationsSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        OrganizationFactory::createOne([
            'name' => 'My organization 2',
        ]);
        OrganizationFactory::createOne([
            'name' => 'My organization 1',
        ]);

        $client->request('GET', '/organizations');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Organizations');
        $this->assertSelectorTextContains('[data-test="organization-item"]:nth-child(1)', 'My organization 1');
        $this->assertSelectorTextContains('[data-test="organization-item"]:nth-child(2)', 'My organization 2');
    }

    public function testGetIndexDisplaysAPlaceholderIfNoOrganization(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->request('GET', '/organizations');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '[data-test="organizations-placeholder"]',
            'No organization'
        );
    }

    public function testGetIndexRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizations');

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->request('GET', '/organizations/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New organization');
    }

    public function testGetNewRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/organizations/new');

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testPostCreateCreatesAnOrganizationAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My organization';

        $client->request('GET', '/organizations/new');
        $crawler = $client->submitForm('form-create-organization-submit', [
            'name' => $name,
        ]);

        $organization = OrganizationFactory::first();
        $this->assertResponseRedirects("/organizations/{$organization->getUid()}", 302);
        $this->assertSame($name, $organization->getName());
        $this->assertSame(20, strlen($organization->getUid()));
    }

    public function testPostCreateFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = '';

        $client->request('GET', '/organizations/new');
        $crawler = $client->submitForm('form-create-organization-submit', [
            'name' => $name,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name is required.');
        $this->assertSame(0, OrganizationFactory::count());
    }

    public function testPostCreateFailsIfNameAlreadyExists(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My organization';
        OrganizationFactory::createOne([
            'name' => $name,
        ]);

        $client->request('GET', '/organizations/new');
        $crawler = $client->submitForm('form-create-organization-submit', [
            'name' => $name,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name "My organization" is already used.');
        $this->assertSame(1, OrganizationFactory::count());
    }

    public function testPostCreateFailsIfNameIsTooLong(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = str_repeat('a', 256);

        $client->request('GET', '/organizations/new');
        $crawler = $client->submitForm('form-create-organization-submit', [
            'name' => $name,
        ]);

        $this->assertSelectorTextContains('#name-error', 'The name must be 255 characters maximum.');
        $this->assertSame(0, OrganizationFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $name = 'My organization';

        $client->request('GET', '/organizations/new');
        $crawler = $client->submitForm('form-create-organization-submit', [
            '_csrf_token' => 'not the token',
            'name' => $name,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $this->assertSame(0, OrganizationFactory::count());
    }

    public function testGetShowRedirectsToTickets(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();

        $client->request('GET', "/organizations/{$organization->getUid()}");

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/tickets", 302);
    }
}
