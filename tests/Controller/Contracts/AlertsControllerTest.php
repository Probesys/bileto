<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Contracts;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AlertsControllerTest extends WebTestCase
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

        $client->request('GET', "/contracts/{$contract->getUid()}/alerts/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Set up contract alerts');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $contract = ContractFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/contracts/{$contract->getUid()}/alerts/edit");
    }

    public function testPostUpdateSavesTheAlerts(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne([
            'startAt' => utils\Time::now(),
            'endAt' => utils\Time::fromNow(1, 'year'),
            'hoursAlert' => 0,
            'dateAlert' => 0,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/alerts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update contract alerts'),
            'hoursAlert' => 80,
            'dateAlert' => 300,
        ]);

        $organization = $contract->getOrganization();
        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/contracts/{$contract->getUid()}", 302);
        $contract->refresh();
        $this->assertSame(80, $contract->getHoursAlert());
        $this->assertSame(300, $contract->getDateAlert());
    }

    public function testPostUpdateDoesNotAcceptNegativeAlerts(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne([
            'startAt' => utils\Time::now(),
            'endAt' => utils\Time::fromNow(1, 'year'),
            'hoursAlert' => 0,
            'dateAlert' => 0,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/alerts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update contract alerts'),
            'hoursAlert' => -10,
            'dateAlert' => -10,
        ]);

        $organization = $contract->getOrganization();
        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/contracts/{$contract->getUid()}", 302);
        $contract->refresh();
        $this->assertSame(0, $contract->getHoursAlert());
        $this->assertSame(0, $contract->getDateAlert());
    }

    public function testPostUpdateForcesMaxAlerts(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne([
            'startAt' => utils\Time::now(),
            'endAt' => utils\Time::fromNow(1, 'year'),
            'hoursAlert' => 0,
            'dateAlert' => 0,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/alerts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update contract alerts'),
            'hoursAlert' => 105,
            'dateAlert' => 400,
        ]);

        $organization = $contract->getOrganization();
        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/contracts/{$contract->getUid()}", 302);
        $contract->refresh();
        $this->assertSame(100, $contract->getHoursAlert());
        $this->assertSame($contract->getDaysDuration(), $contract->getDateAlert());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $contract = ContractFactory::createOne([
            'startAt' => utils\Time::now(),
            'endAt' => utils\Time::fromNow(1, 'year'),
            'hoursAlert' => 0,
            'dateAlert' => 0,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/alerts/edit", [
            '_csrf_token' => 'not the token',
            'hoursAlert' => 80,
            'dateAlert' => 300,
        ]);

        $organization = $contract->getOrganization();
        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $contract->refresh();
        $this->assertSame(0, $contract->getHoursAlert());
        $this->assertSame(0, $contract->getDateAlert());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $contract = ContractFactory::createOne([
            'startAt' => utils\Time::now(),
            'endAt' => utils\Time::fromNow(1, 'year'),
            'hoursAlert' => 0,
            'dateAlert' => 0,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/contracts/{$contract->getUid()}/alerts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update contract alerts'),
            'hoursAlert' => 80,
            'dateAlert' => 300,
        ]);
    }
}