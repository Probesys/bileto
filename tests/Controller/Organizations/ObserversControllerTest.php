<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Entity;
use App\Tests;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ObserversControllerTest extends WebTestCase
{
    use Tests\AuthorizationHelper;
    use Tests\SessionHelper;
    use Factories;
    use ResetDatabase;

    public function testPostSwitchAddsObserverToOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $observer,
        ) = Tests\Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Tests\Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:update:tickets:actors',
            'orga:see:users',
        ], $organization->_real());
        $this->grantOrga($observer->_real(), ['orga:see'], $organization->_real());

        $client->request(
            Request::METHOD_POST,
            "/organizations/{$organization->getUid()}/observers/{$observer->getUid()}/switch",
            [
                '_csrf_token' => $this->generateCsrfToken($client, 'switch organization observer'),
            ],
        );

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/users", 302);
        $organization->_refresh();
        $orgaObservers = $organization->getObservers();
        $this->assertSame(1, count($orgaObservers));
        $this->assertSame($observer->getUid(), $orgaObservers[0]->getUid());
    }

    public function testPostSwitchRemoveObserverFromOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $observer,
        ) = Tests\Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Tests\Factory\OrganizationFactory::createOne([
            'observers' => [$observer],
        ]);
        $this->grantOrga($user->_real(), [
            'orga:update:tickets:actors',
            'orga:see:users',
        ], $organization->_real());
        $this->grantOrga($observer->_real(), ['orga:see'], $organization->_real());

        $client->request(
            Request::METHOD_POST,
            "/organizations/{$organization->getUid()}/observers/{$observer->getUid()}/switch",
            [
                '_csrf_token' => $this->generateCsrfToken($client, 'switch organization observer'),
            ],
        );

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/users", 302);
        $organization->_refresh();
        $orgaObservers = $organization->getObservers();
        $this->assertSame(0, count($orgaObservers));
    }

    public function testPostSwitchFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        list(
            $user,
            $observer,
        ) = Tests\Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Tests\Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:update:tickets:actors',
            'orga:see:users',
        ], $organization->_real());
        $this->grantOrga($observer->_real(), ['orga:see'], $organization->_real());

        $client->request(
            Request::METHOD_POST,
            "/organizations/{$organization->getUid()}/observers/{$observer->getUid()}/switch",
            [
                '_csrf_token' => 'not a token',
            ],
        );

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/users", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        $organization->_refresh();
        $orgaObservers = $organization->getObservers();
        $this->assertSame(0, count($orgaObservers));
    }

    public function testPostSwitchFailsIfUserCannotAccessOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $observer,
        ) = Tests\Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Tests\Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:update:tickets:actors',
            'orga:see:users',
        ], $organization->_real());

        $client->request(
            Request::METHOD_POST,
            "/organizations/{$organization->getUid()}/observers/{$observer->getUid()}/switch",
            [
                '_csrf_token' => $this->generateCsrfToken($client, 'switch organization observer'),
            ],
        );

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/users", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The user is not authorized to access this organization');
        $organization->_refresh();
        $orgaObservers = $organization->getObservers();
        $this->assertSame(0, count($orgaObservers));
    }

    public function testPostSwitchFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $observer,
        ) = Tests\Factory\UserFactory::createMany(2);
        $client->loginUser($user->_real());
        $organization = Tests\Factory\OrganizationFactory::createOne();
        $this->grantOrga($observer->_real(), ['orga:see'], $organization->_real());

        $client->catchExceptions(false);
        $client->request(
            Request::METHOD_POST,
            "/organizations/{$organization->getUid()}/observers/{$observer->getUid()}/switch",
            [
                '_csrf_token' => $this->generateCsrfToken($client, 'switch organization observer'),
            ],
        );
    }
}
