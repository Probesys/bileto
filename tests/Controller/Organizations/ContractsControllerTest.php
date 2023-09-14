<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContractsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), ['orga:see:contracts']);
        $endAt1 = new \DateTimeImmutable('2023-10-01');
        $contract1 = ContractFactory::createOne([
            'name' => 'My contract 1',
            'organization' => $organization,
            'endAt' => $endAt1,
        ]);
        $endAt2 = new \DateTimeImmutable('2023-09-01');
        $contract2 = ContractFactory::createOne([
            'name' => 'My contract 2',
            'organization' => $organization,
            'endAt' => $endAt2,
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/contracts");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="contract-item"]:nth-child(1)', 'My contract 1');
        $this->assertSelectorTextContains('[data-test="contract-item"]:nth-child(2)', 'My contract 2');
    }

    public function testGetIndexListsContractsFromParentOrganizations(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $subOrganization = OrganizationFactory::createOne([
            'parentsPath' => "/{$organization->getId()}/",
        ]);
        $this->grantOrga($user->object(), ['orga:see:contracts']);
        $contract = ContractFactory::createOne([
            'name' => 'My contract',
            'organization' => $organization,
        ]);

        $client->request('GET', "/organizations/{$subOrganization->getUid()}/contracts");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="contract-item"]', 'My contract');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);
        $this->grantOrga($user->object(), ['orga:see'], $organization->object());
        $contract = ContractFactory::createOne([
            'name' => 'My contract',
            'organization' => $organization,
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/organizations/{$organization->getUid()}/contracts");
    }
}
