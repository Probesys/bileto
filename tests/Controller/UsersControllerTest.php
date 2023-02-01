<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UsersControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetIndexListsUsersSortedByNameAndEmail(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne([
            'name' => 'Charlie Gature',
        ]);
        UserFactory::createOne([
            'name' => 'Benedict Aphone',
        ]);
        UserFactory::createOne([
            'name' => '',
            'email' => 'alix.pataques@example.com',
        ]);
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), ['admin:manage:users']);

        $client->request('GET', '/users');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Users');
        $this->assertSelectorTextContains('[data-test="user-item"]:nth-child(1)', 'alix.pataques@example.com');
        $this->assertSelectorTextContains('[data-test="user-item"]:nth-child(2)', 'Benedict Aphone');
        $this->assertSelectorTextContains('[data-test="user-item"]:nth-child(3)', 'Charlie Gature');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', '/users');
    }
}
