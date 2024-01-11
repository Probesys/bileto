<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
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

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);

        $client->request('GET', "/organizations/{$organization->getUid()}/contracts/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New contract');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:see:contracts',
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/organizations/{$organization->getUid()}/contracts/new");
    }

    public function testPostCreateCreatesAContractAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = 'My contract';
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $billingInterval = 30;
        $notes = 'Some notes';

        $this->assertSame(0, ContractFactory::count());

        $client->request('POST', "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'billingInterval' => $billingInterval,
                'notes' => $notes,
            ],
        ]);

        $this->assertResponseRedirects("/organizations/{$organization->getUid()}/contracts", 302);
        $this->assertSame(1, ContractFactory::count());
        $contract = ContractFactory::first();
        $this->assertSame($name, $contract->getName());
        $this->assertSame($maxHours, $contract->getMaxHours());
        $expectedStartAt = $startAt->modify('00:00:00');
        $this->assertEquals($startAt, $contract->getStartAt());
        $expectedEndAt = $endAt->modify('23:59:59');
        $this->assertEquals($expectedEndAt, $contract->getEndAt());
        $this->assertSame($billingInterval, $contract->getBillingInterval());
        $this->assertSame($notes, $contract->getNotes());
        $this->assertSame(80, $contract->getHoursAlert());
        $this->assertSame(24, $contract->getDateAlert()); // 20% of the days duration
    }

    public function testPostCreateFailsIfNameIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = str_repeat('a', 256);
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $notes = 'Some notes';

        $client->request('POST', "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'notes' => $notes,
            ],
        ]);

        $this->assertSelectorTextContains('#contract_name-error', 'Enter a name of less than 255 characters.');
        $this->assertSame(0, ContractFactory::count());
    }

    public function testPostCreateFailsIfDatesAreInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = str_repeat('a', 256);
        $maxHours = 10;
        $endAt = new \DateTimeImmutable('2023-12-31');
        $notes = 'Some notes';

        $client->request('POST', "/organizations/{$organization->getUid()}/contracts/new", [
            '_csrf_token' => $this->generateCsrfToken($client, 'create organization contract'),
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => 'not-a-date',
                'endAt' => $endAt->format('Y-m-d'),
                'notes' => $notes,
            ],
        ]);

        $this->assertSelectorTextContains('#contract_startAt-error', 'Please enter a valid date');
        $this->assertSame(0, ContractFactory::count());
    }

    public function testPostCreateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = 'My contract';
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $notes = 'Some notes';

        $client->request('POST', "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'not a token'),
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'notes' => $notes,
            ],
        ]);

        $this->assertSelectorTextContains('#contract-error', 'The security token is invalid');
        $this->assertSame(0, ContractFactory::count());
    }

    public function testPostCreateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $this->grantOrga($user->object(), [
            'orga:see:contracts',
        ]);
        $name = 'My contract';
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $notes = 'Some notes';

        $client->catchExceptions(false);
        $client->request('POST', "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'notes' => $notes,
            ],
        ]);
    }
}
