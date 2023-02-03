<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SettingsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetIndexRedirectsToOrganizations(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), [
            'admin:see',
            'admin:manage:roles',
            'admin:manage:users',
            'admin:manage:organizations',
        ]);

        $client->request('GET', '/settings');

        $this->assertResponseRedirects('/organizations', 302);
    }

    public function testGetIndexRedirectsToRolesIfNoPermissionToOrganizations(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), [
            'admin:see',
            'admin:manage:roles',
            'admin:manage:users',
        ]);

        $client->request('GET', '/settings');

        $this->assertResponseRedirects('/roles', 302);
    }

    public function testGetIndexDisplaysAnErrorIfNoPermissionsAreGiven(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantAdmin($user->object(), [
            'admin:see',
        ]);

        $client->request('GET', '/settings');

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
        $client->request('GET', '/settings');
    }
}
