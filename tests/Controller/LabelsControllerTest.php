<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
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

    public function testPostNewCreatesTheLabelAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $name = 'My label';
        $description = 'My description';
        $color = 'primary';

        $this->assertSame(0, LabelFactory::count());

        $crawler = $client->request(Request::METHOD_POST, '/labels/new', [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $name,
                'description' => $description,
                'color' => $color,
            ],
        ]);

        $this->assertSame(1, LabelFactory::count());
        $label = LabelFactory::last();
        $this->assertResponseRedirects('/labels', 302);
        $this->assertSame($name, $label->getName());
        $this->assertSame($description, $label->getDescription());
        $this->assertSame($color, $label->getColor());
        $this->assertSame(20, strlen($label->getUid()));
    }

    public function testPostNewFailsIfNameIsAlreadyUsed(): void
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
                'color' => 'grey',
            ],
        ]);

        $this->assertSelectorTextContains(
            '#label_name-error',
            'Enter a different name, a label already has this name',
        );
        $this->assertSame(1, LabelFactory::count());
    }

    public function testPostNewFailsIfNameIsEmpty(): void
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
                'color' => 'grey',
            ],
        ]);

        $this->assertSelectorTextContains('#label_name-error', 'Enter a name');
        $this->assertSame(0, LabelFactory::count());
    }

    public function testPostNewFailsIfColorIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $name = 'My label';
        $color = 'not a color';

        $crawler = $client->request(Request::METHOD_POST, '/labels/new', [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $name,
                'color' => $color,
            ],
        ]);

        $this->assertSelectorTextContains('#label_color-error', 'The selected choice is invalid');
        $this->assertSame(0, LabelFactory::count());
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
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
                'color' => 'grey',
            ],
        ]);

        $this->assertSelectorTextContains('#label-error', 'The security token is invalid');
        $this->assertSame(0, LabelFactory::count());
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $label = LabelFactory::createOne();

        $client->request(Request::METHOD_GET, "/labels/{$label->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit a label');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $label = LabelFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/labels/{$label->getUid()}/edit");
    }

    public function testPostEditSavesTheLabelAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $initialName = 'label';
        $newName = 'My label';
        $initialDescription = 'description';
        $newDescription = 'My description';
        $initialColor = 'primary';
        $newColor = 'red';
        $label = LabelFactory::createOne([
            'name' => $initialName,
            'description' => $initialDescription,
            'color' => $initialColor,
        ]);

        $client->request(Request::METHOD_POST, "/labels/{$label->getUid()}/edit", [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $newName,
                'description' => $newDescription,
                'color' => $newColor,
            ],
        ]);

        $this->assertResponseRedirects('/labels', 302);
        $label->_refresh();
        $this->assertSame($newName, $label->getName());
        $this->assertSame($newDescription, $label->getDescription());
        $this->assertSame($newColor, $label->getColor());
    }

    public function testPostEditFailsIfNameIsAlreadyUsed(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $initialName = 'label';
        $newName = 'My label';
        $label = LabelFactory::createOne([
            'name' => $initialName,
        ]);
        LabelFactory::createOne([
            'name' => $newName,
        ]);

        $client->request(Request::METHOD_POST, "/labels/{$label->getUid()}/edit", [
            'label' => [
                '_token' => $this->generateCsrfToken($client, 'label'),
                'name' => $newName,
                'color' => 'grey',
            ],
        ]);

        $this->assertSelectorTextContains(
            '#label_name-error',
            'Enter a different name, a label already has this name',
        );
        $this->clearEntityManager();
        $label->_refresh();
        $this->assertSame($initialName, $label->getName());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $initialName = 'label';
        $newName = 'My label';
        $label = LabelFactory::createOne([
            'name' => $initialName,
        ]);

        $client->request(Request::METHOD_POST, "/labels/{$label->getUid()}/edit", [
            'label' => [
                '_token' => 'not a token',
                'name' => $newName,
                'color' => 'grey',
            ],
        ]);

        $this->assertSelectorTextContains('#label-error', 'The security token is invalid');
        $this->clearEntityManager();
        $label->_refresh();
        $this->assertSame($initialName, $label->getName());
    }

    public function testPostDeleteRemovesTheLabelAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $label = LabelFactory::createOne();

        $this->clearEntityManager();

        $client->request(Request::METHOD_POST, "/labels/{$label->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete label'),
        ]);

        $this->assertResponseRedirects('/labels', 302);
        LabelFactory::assert()->notExists(['id' => $label->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantAdmin($user->_real(), ['admin:manage:labels']);
        $label = LabelFactory::createOne();

        $client->request(Request::METHOD_POST, "/labels/{$label->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertResponseRedirects("/labels/{$label->getUid()}/edit", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        LabelFactory::assert()->exists(['id' => $label->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $label = LabelFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/labels/{$label->getUid()}/deletion", [
            '_csrf_token' => $this->generateCsrfToken($client, 'delete label'),
        ]);
    }
}
