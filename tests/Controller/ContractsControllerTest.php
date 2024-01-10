<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\TimeSpentFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContractsControllerTest extends WebTestCase
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

        $client->request('GET', "/contracts/{$contract->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit a contract');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $contract = ContractFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/contracts/{$contract->getUid()}/edit");
    }

    public function testPostUpdateSavesTheContract(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $oldName = 'Contract 2023';
        $newName = 'Contract 2024';
        $oldMaxHours = 5;
        $newMaxHours = 10;
        $contract = ContractFactory::createOne([
            'name' => $oldName,
            'maxHours' => $oldMaxHours,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/edit", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $newName,
                'maxHours' => $newMaxHours,
            ],
        ]);

        $organization = $contract->getOrganization();
        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/contracts/{$contract->getUid()}", 302);
        $contract->refresh();
        $this->assertSame($newName, $contract->getName());
        $this->assertSame($newMaxHours, $contract->getMaxHours());
    }

    public function testPostUpdateDoesNotAcceptMaxHoursBelowSpentTime(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $oldName = 'Contract 2023';
        $newName = 'Contract 2024';
        $oldMaxHours = 10;
        $newMaxHours = 1;
        $contract = ContractFactory::createOne([
            'name' => $oldName,
            'maxHours' => $oldMaxHours,
        ]);
        $timeSpent = TimeSpentFactory::createOne([
            'time' => ($newMaxHours + 1) * 60,
            'contract' => $contract,
        ]);

        $response = $client->request('POST', "/contracts/{$contract->getUid()}/edit", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $newName,
                'maxHours' => $newMaxHours,
            ]
        ]);

        $this->assertSelectorTextContains(
            '#contract_maxHours-error',
            'Enter a number of hours greater than 2.'
        );
        $contract->refresh();
        $this->assertSame($oldName, $contract->getName());
        $this->assertSame($oldMaxHours, $contract->getMaxHours());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:manage:contracts']);
        $oldName = 'Contract 2023';
        $newName = 'Contract 2024';
        $oldMaxHours = 5;
        $newMaxHours = 10;
        $contract = ContractFactory::createOne([
            'name' => $oldName,
            'maxHours' => $oldMaxHours,
        ]);

        $client->request('POST', "/contracts/{$contract->getUid()}/edit", [
            'contract' => [
                '_token' => 'not a token',
                'name' => $newName,
                'maxHours' => $newMaxHours,
            ],
        ]);

        $this->assertSelectorTextContains('#contract-error', 'The security token is invalid');
        $contract->refresh();
        $this->assertSame($oldName, $contract->getName());
        $this->assertSame($oldMaxHours, $contract->getMaxHours());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $oldName = 'Contract 2023';
        $newName = 'Contract 2024';
        $oldMaxHours = 5;
        $newMaxHours = 10;
        $contract = ContractFactory::createOne([
            'name' => $oldName,
            'maxHours' => $oldMaxHours,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/contracts/{$contract->getUid()}/edit", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $newName,
                'maxHours' => $newMaxHours,
            ],
        ]);
    }
}
