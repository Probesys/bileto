<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FiltersControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetEditWithStatusRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('GET', '/tickets/filters/status/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit “status” filter');
    }

    public function testGetEditWithTypeRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('GET', '/tickets/filters/type/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit “type” filter');
    }

    public function testGetEditWithPriorityRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filter = Factory::faker()->randomElement(['priority', 'urgency', 'impact']);

        $crawler = $client->request('GET', "/tickets/filters/{$filter}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit “priority” filters');
    }

    public function testGetEditWithActorsRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $filter = Factory::faker()->randomElement(['actors', 'assignee', 'requester', 'involves']);

        $crawler = $client->request('GET', "/tickets/filters/{$filter}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit “actors” filters');
    }

    public function testGetEditWithQueryRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('GET', '/tickets/filters/status/edit', [
            'query' => 'status:open',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('query', 'status:open');
    }

    public function testGetEditWithInvalidQueryRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('GET', '/tickets/filters/status/edit', [
            'query' => 'status:(open)',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('query', 'status:(open)');
    }

    public function testGetEditFailsWithUnsupportedFilter(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/tickets/filters/unsupported/edit');
    }

    public function testPostCombineAddsTextToQueryAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'text' => 'foo',
            'query' => 'status:open',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('foo status:open');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineAddsFilterToQueryAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'filters' => [
                'status' => ['new', 'in_progress'],
            ],
            'query' => 'status:open',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('status:new,in_progress');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineWithEmptyText(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'text' => '',
            'query' => 'foo',
            'from' => '/tickets',
        ]);

        $expectedQuery = '';
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineWithEmptyFilter(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'filters' => [
                'type' => [''],
            ],
            'query' => 'status:open type:incident',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('status:open');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineWithNoAssignee(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'filters' => [
                'assignee' => ['no'],
            ],
            'query' => 'status:open assignee:@me',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('status:open no:assignee');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineResetsQueryIfInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'text' => 'foo',
            'query' => 'status:(open)',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('foo');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineResetsQueryIfUnsupported(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'text' => 'foo',
            'query' => 'org:#1',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('foo');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineDoesNotCombineUnsupportedFilters(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'filters' => [
                'org' => ['#1'],
            ],
            'query' => 'status:open',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('status:open');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineDoesNotCombineNotArrayFilters(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'filters' => [
                'type' => 'incident',
            ],
            'query' => 'status:open',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('status:open');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineDoesNotCombineFiltersWithInvalidValues(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $crawler = $client->request('POST', '/tickets/filters/combine', [
            'filters' => [
                'assignee' => ['1'],
            ],
            'query' => 'status:open',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('status:open');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineFailsIfFromIsNotRedirectable(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('POST', '/tickets/filters/combine', [
            'text' => 'foo',
            'query' => 'status:open',
            'from' => '/does-not-exist',
        ]);
    }
}
