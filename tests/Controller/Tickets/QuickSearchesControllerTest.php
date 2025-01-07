<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class QuickSearchesControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;

    public function testPostNewWithText(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/searches', [
            'search' => [
                'text' => 'foo',
                'from' => '/tickets',
            ],
        ]);

        $expectedQuery = urlencode('foo');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostNewWithEmptyText(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/searches', [
            'search' => [
                'text' => '',
                'from' => '/tickets',
            ],
        ]);

        $expectedQuery = '';
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostNewWithStatuses(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/searches', [
            'search' => [
                'statuses' => ['new', 'in_progress'],
                'from' => '/tickets',
            ],
        ]);

        $expectedQuery = urlencode('status:new,in_progress');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostNewWithUnassignedOnly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see'], type: 'agent');

        $crawler = $client->request(Request::METHOD_POST, '/tickets/searches', [
            'search' => [
                'unassignedOnly' => true,
                'from' => '/tickets',
            ],
        ]);

        $expectedQuery = urlencode('no:assignee');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostNewWithLabels(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $fooLabel = Factory\LabelFactory::createOne([
            'name' => 'foo',
        ]);
        $barLabel = Factory\LabelFactory::createOne([
            'name' => 'bar',
        ]);

        $crawler = $client->request(Request::METHOD_POST, '/tickets/searches', [
            'search' => [
                'labels' => [$fooLabel->getId(), $barLabel->getId()],
                'from' => '/tickets',
            ],
        ]);

        $expectedQuery = urlencode('label:bar label:foo');
        $this->assertResponseRedirects("/tickets?q={$expectedQuery}", 302);
    }

    public function testPostNewWithInvalidData(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $crawler = $client->request(Request::METHOD_POST, '/tickets/searches', [
            'search' => [
                'statuses' => ['foo'],
                'from' => '/tickets',
            ],
        ]);

        $this->assertResponseRedirects('/tickets', 302);
    }

    public function testPostNewFailsIfFromIsNotRedirectable(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, '/tickets/searches', [
            'search' => [
                'from' => '/does-not-exist',
            ],
        ]);
    }
}
