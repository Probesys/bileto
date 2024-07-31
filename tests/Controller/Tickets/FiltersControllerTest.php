<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zenstruck\Foundry\Factory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FiltersControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testPostCombineAddsTextToQueryAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
            'filters' => [
                'assignee' => ['no'],
            ],
            'query' => 'status:open assignee:@me',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('status:open no:assignee');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineWithLabels(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
            'filters' => [
                'label' => ['spam', 'egg'],
            ],
            'query' => 'label:foo label:bar',
            'from' => '/tickets',
        ]);

        $expectedQuery = urlencode('label:spam label:egg');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostCombineResetsQueryIfInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
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
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, '/tickets/filters/combine', [
            'text' => 'foo',
            'query' => 'status:open',
            'from' => '/does-not-exist',
        ]);
    }
}
