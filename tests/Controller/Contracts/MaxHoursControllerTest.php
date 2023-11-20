<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MaxHoursControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne();

        $client->request('GET', "/contracts/{$contract->getUid()}/max-hours/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Extend the number of hours of the contract');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $contract = ContractFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/contracts/{$contract->getUid()}/max-hours/edit");
    }

    public function testPostUpdateSavesTheMaxHours(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne([
            'maxHours' => 10,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/max-hours/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update contract max hours'),
            'additionalHours' => 5,
        ]);

        $organization = $contract->getOrganization();
        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/contracts/{$contract->getUid()}", 302);
        $contract->refresh();
        $this->assertSame(15, $contract->getMaxHours());
    }

    public function testPostUpdateDoesNotAcceptNegativeAdditionalHours(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne([
            'maxHours' => 10,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/max-hours/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update contract max hours'),
            'additionalHours' => -5,
        ]);

        $organization = $contract->getOrganization();
        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/contracts/{$contract->getUid()}", 302);
        $contract->refresh();
        $this->assertSame(10, $contract->getMaxHours());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne([
            'maxHours' => 10,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/max-hours/edit", [
            '_csrf_token' => 'not the token',
            'additionalHours' => 5,
        ]);

        $organization = $contract->getOrganization();
        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $contract->refresh();
        $this->assertSame(10, $contract->getMaxHours());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $contract = ContractFactory::createOne([
            'maxHours' => 10,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/contracts/{$contract->getUid()}/max-hours/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update contract max hours'),
            'additionalHours' => 5,
        ]);
    }
}
