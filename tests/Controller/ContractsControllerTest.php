<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContractsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:contracts']);
        $contract1 = Factory\ContractFactory::createOne([
            'name' => 'My contract 1',
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(1, 'months'),
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'name' => 'My contract 2',
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(2, 'months'),
        ]);

        $client->request(Request::METHOD_GET, '/contracts');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="contract-item"]:nth-child(1)', 'My contract 2');
        $this->assertSelectorTextContains('[data-test="contract-item"]:nth-child(2)', 'My contract 1');
    }

    public function testGetIndexRendersCorrectlyListsOnlyAccessibleContracts(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization1 = Factory\OrganizationFactory::createOne();
        $organization2 = Factory\OrganizationFactory::createOne();
        $organization3 = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see',
            'orga:see:contracts'
        ], $organization1->_real());
        $this->grantOrga($user->_real(), [
            'orga:see',
        ], $organization2->_real());
        $contract1 = Factory\ContractFactory::createOne([
            'name' => 'My contract 1',
            'organization' => $organization1,
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(1, 'months'),
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'name' => 'My contract 2',
            'organization' => $organization2,
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(1, 'months'),
        ]);
        $contract3 = Factory\ContractFactory::createOne([
            'name' => 'My contract 3',
            'organization' => $organization3,
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(1, 'months'),
        ]);

        $crawler = $client->request(Request::METHOD_GET, '/contracts');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="contract-item"]', 'My contract 1');
        $this->assertSelectorNotExists('[data-test="contract-item"]:nth-child(2)');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see']);
        $contract = Factory\ContractFactory::createOne([
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(1, 'months'),
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, '/contracts');
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:manage:contracts']);
        $contract = Factory\ContractFactory::createOne();

        $client->request(Request::METHOD_GET, "/contracts/{$contract->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit a contract');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $contract = Factory\ContractFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/contracts/{$contract->getUid()}/edit");
    }

    public function testPostEditSavesTheContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:manage:contracts']);
        $oldName = 'Contract 2023';
        $newName = 'Contract 2024';
        $oldMaxHours = 5;
        $newMaxHours = 10;
        $contract = Factory\ContractFactory::createOne([
            'name' => $oldName,
            'maxHours' => $oldMaxHours,
        ]);

        $client->request(Request::METHOD_POST, "/contracts/{$contract->getUid()}/edit", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $newName,
                'maxHours' => $newMaxHours,
            ],
        ]);

        $this->assertResponseRedirects("/contracts/{$contract->getUid()}", 302);
        $contract->_refresh();
        $this->assertSame($newName, $contract->getName());
        $this->assertSame($newMaxHours, $contract->getMaxHours());
    }

    public function testPostEditDoesNotAcceptMaxHoursBelowSpentTime(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:manage:contracts']);
        $oldName = 'Contract 2023';
        $newName = 'Contract 2024';
        $oldMaxHours = 10;
        $newMaxHours = 1;
        $contract = Factory\ContractFactory::createOne([
            'name' => $oldName,
            'maxHours' => $oldMaxHours,
        ]);
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'time' => ($newMaxHours + 1) * 60,
            'contract' => $contract,
        ]);

        $response = $client->request(Request::METHOD_POST, "/contracts/{$contract->getUid()}/edit", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $newName,
                'maxHours' => $newMaxHours,
            ]
        ]);

        $this->assertSelectorTextContains(
            '#contract_maxHours-error',
            'Enter a number of hours greater than or equal 2.'
        );
        $this->clearEntityManager();
        $contract->_refresh();
        $this->assertSame($oldName, $contract->getName());
        $this->assertSame($oldMaxHours, $contract->getMaxHours());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:manage:contracts']);
        $oldName = 'Contract 2023';
        $newName = 'Contract 2024';
        $oldMaxHours = 5;
        $newMaxHours = 10;
        $contract = Factory\ContractFactory::createOne([
            'name' => $oldName,
            'maxHours' => $oldMaxHours,
        ]);

        $client->request(Request::METHOD_POST, "/contracts/{$contract->getUid()}/edit", [
            'contract' => [
                '_token' => 'not a token',
                'name' => $newName,
                'maxHours' => $newMaxHours,
            ],
        ]);

        $this->assertSelectorTextContains('#contract-error', 'The security token is invalid');
        $this->clearEntityManager();
        $contract->_refresh();
        $this->assertSame($oldName, $contract->getName());
        $this->assertSame($oldMaxHours, $contract->getMaxHours());
    }
}
