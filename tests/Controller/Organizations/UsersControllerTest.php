<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity\User;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UsersControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetIndexListsUsersSortedByNameAndEmail(): void
    {
        $client = static::createClient();
        $organization = OrganizationFactory::createOne();
        $user1 = UserFactory::createOne([
            'name' => 'Charlie Gature',
        ]);
        $user2 = UserFactory::createOne([
            'name' => 'Benedict Aphone',
        ]);
        $user3 = UserFactory::createOne([
            'name' => 'Alix Hambourg',
        ]);
        $client->loginUser($user1->object());
        $this->grantAdmin($user1->object(), ['admin:manage:users']);
        $this->grantOrga(
            $user1->object(),
            ['orga:see:users'],
            $organization->object(),
            'agent',
        );
        $this->grantOrga(
            $user3->object(),
            ['orga:see'],
            $organization->object(),
            'user',
        );

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/users");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Users');
        $this->assertSelectorTextContains('[data-test="user-item"]', 'Alix Hambourg');
        $this->assertSelectorNotExists('[data-test="user-item"]:nth-child(2)');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $organization = OrganizationFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/users");
    }
}
