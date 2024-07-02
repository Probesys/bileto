<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AdminControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetIndexRedirectsToRoles(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), [
            'admin:see',
            'admin:manage:roles',
            'admin:manage:users',
            'admin:create:organizations',
        ]);

        $client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseRedirects('/roles', 302);
    }

    public function testGetIndexRedirectsToUsersIfNoPermissionToRoles(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), [
            'admin:see',
            'admin:manage:users',
            'admin:create:organizations',
        ]);

        $client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseRedirects('/users', 302);
    }

    public function testGetIndexDisplaysAnErrorIfNoPermissionsAreGiven(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), [
            'admin:see',
        ]);

        $client->request(Request::METHOD_GET, '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '[data-test="alert-warning"]',
            'You don’t have access to the administration.'
        );
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/admin');
    }
}
