<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\Label;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\LabelFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\FactoriesHelper;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LabelsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsLabelsSortedByName(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $label1 = LabelFactory::createOne([
            'name' => 'foo',
        ]);
        $label2 = LabelFactory::createOne([
            'name' => 'bar',
        ]);

        $client->request(Request::METHOD_GET, '/labels');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Label');
        $this->assertSelectorTextContains('[data-test="label-item"]:nth-child(1)', 'bar');
        $this->assertSelectorTextContains('[data-test="label-item"]:nth-child(2)', 'foo');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/labels');
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);

        $client->request(Request::METHOD_GET, '/labels/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New label');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/labels/new');
    }

    public function testPostCreateCreatesTheLabelAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $name = 'My label';
        $description = 'My description';

        $this->assertSame(0, LabelFactory::count());

        $crawler = $client->request(Request::METHOD_POST, '/labels/new', [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $name,
                'description' => $description,
                'color' => '#e0e1e6',
            ],
        ]);

        $this->assertSame(1, LabelFactory::count());
        $label = LabelFactory::last();
        $this->assertResponseRedirects('/labels', 302);
        $this->assertSame($name, $label->getName());
        $this->assertSame($description, $label->getDescription());
        $this->assertSame(20, strlen($label->getUid()));
    }

    public function testPostCreateFailsIfNameIsAlreadyUsed(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $name = 'My label';
        LabelFactory::createOne([
            'name' => $name,
        ]);

        $crawler = $client->request(Request::METHOD_POST, '/labels/new', [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $name,
                'color' => '#e0e1e6',
            ],
        ]);

        $this->assertSelectorTextContains(
            '#label_name-error',
            'Enter a different name, a label already has this name',
        );
        $this->assertSame(1, LabelFactory::count());
    }

    public function testPostCreateFailsIfNameIsEmpty(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $name = '';

        $crawler = $client->request(Request::METHOD_POST, '/labels/new', [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $name,
                'color' => '#e0e1e6',
            ],
        ]);

        $this->assertSelectorTextContains('#label_name-error', 'Enter a name');
        $this->assertSame(0, LabelFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $name = 'My label';

        $crawler = $client->request(Request::METHOD_POST, '/labels/new', [
            'label' => [
                '_token' => 'not a token',
                'name' => $name,
                'color' => '#e0e1e6',
            ],
        ]);

        $this->assertSelectorTextContains('#label-error', 'The security token is invalid');
        $this->assertSame(0, LabelFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $name = 'My label';

        $client->catchExceptions(false);
        $crawler = $client->request(Request::METHOD_POST, '/labels/new', [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $name,
                'color' => '#e0e1e6',
            ],
        ]);
    }
}
