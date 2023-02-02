<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Users;

use App\Entity\User;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthorizationsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetIndexListsAuthorizationsSortedByRolesAndOrgasNames(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $roleA = RoleFactory::createOne([
            'name' => 'Role A',
            'type' => 'admin',
            'permissions' => ['admin:manage:users'],
        ]);
        $roleB = RoleFactory::createOne([
            'name' => 'Role B',
            'type' => 'orga',
        ]);
        $orgaA = OrganizationFactory::createOne([
            'name' => 'Orga A',
        ]);
        $orgaB = OrganizationFactory::createOne([
            'name' => 'Orga B',
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleB,
            'organization' => $orgaB,
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleB,
            'organization' => $orgaA,
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleB,
        ]);
        AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $roleA,
        ]);

        $client->request('GET', "/users/{$user->getUid()}/authorizations");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Authorizations');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(1)', 'Role A');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(2)', 'Role B * global *');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(3)', 'Role B Orga A');
        $this->assertSelectorTextContains('[data-test="authorization-item"]:nth-child(4)', 'Role B Orga B');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request('GET', "/users/{$user->getUid()}/authorizations");
    }
}
