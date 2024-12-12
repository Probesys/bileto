<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Organizations;

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
    use Tests\SessionHelper;

    public function testGetIndexRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:see:contracts']);
        $contract1 = Factory\ContractFactory::createOne([
            'name' => 'My contract 1',
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(1, 'months'),
        ]);
        $contract2 = Factory\ContractFactory::createOne([
            'name' => 'My contract 2',
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'months'),
            'endAt' => Utils\Time::fromNow(2, 'months'),
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/contracts");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-test="contract-item"]:nth-child(1)', 'My contract 2');
        $this->assertSelectorTextContains('[data-test="contract-item"]:nth-child(2)', 'My contract 1');
    }

    public function testGetIndexFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne([
            'name' => 'My organization',
        ]);
        $this->grantOrga($user->_real(), ['orga:see'], $organization->_real());
        $contract = Factory\ContractFactory::createOne([
            'name' => 'My contract',
            'organization' => $organization,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/contracts");
    }

    public function testGetNewRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);

        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/contracts/new");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'New contract');
    }

    public function testGetNewFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/contracts/new");
    }

    public function testGetNewFailsIfRenewedContractIsAlreadyRenewed(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You cannot renew a contract that has already been renewed');

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $renewedContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'renewedBy' => Factory\ContractFactory::createOne(),
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/organizations/{$organization->getUid()}/contracts/new", [
            'from' => $renewedContract->getUid(),
        ]);
    }

    public function testPostNewCreatesAContractAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = 'My contract';
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $timeAccountingUnit = 30;
        $notes = 'Some notes';

        $this->assertSame(0, Factory\ContractFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => $name,
                'maxHours' => $maxHours,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'timeAccountingUnit' => $timeAccountingUnit,
                'notes' => $notes,
            ],
        ]);

        $this->assertSame(1, Factory\ContractFactory::count());
        $contract = Factory\ContractFactory::first();
        $this->assertResponseRedirects("/contracts/{$contract->getUid()}", 302);
        $this->assertSame($name, $contract->getName());
        $this->assertSame($maxHours, $contract->getMaxHours());
        $expectedStartAt = $startAt->modify('00:00:00');
        $this->assertEquals($startAt, $contract->getStartAt());
        $expectedEndAt = $endAt->modify('23:59:59');
        $this->assertEquals($expectedEndAt, $contract->getEndAt());
        $this->assertSame($timeAccountingUnit, $contract->getTimeAccountingUnit());
        $this->assertSame($notes, $contract->getNotes());
        $this->assertSame(80, $contract->getHoursAlert());
        $this->assertSame(24, $contract->getDateAlert()); // 20% of the days duration
    }

    public function testPostNewCanAttachTicketsToContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $startAt = new \DateTimeImmutable('2023-01-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $ticket = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'createdAt' => new \DateTimeImmutable('2023-06-06'),
        ]);

        $this->assertSame(0, Factory\ContractFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => 'My contract',
                'maxHours' => 10,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'associateTickets' => true,
            ],
        ]);

        $this->assertSame(1, Factory\ContractFactory::count());
        $contract = Factory\ContractFactory::last();
        $contract->_refresh();
        $this->assertResponseRedirects("/contracts/{$contract->getUid()}", 302);
        $tickets = $contract->getTickets();
        $this->assertSame(1, count($tickets));
        $this->assertSame($ticket->getUid(), $tickets[0]->getUid());
    }

    public function testPostNewDoesNotAttachContractIfTicketsHaveAlreadyOneOngoing(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $startAt = new \DateTimeImmutable('2023-01-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        // We use an overlapping contract. Note that the ticket has been
        // created before the start of the existing contract. It allows to test
        // an edge-case that we want to handle correctly.
        $existingStartAt = new \DateTimeImmutable('2023-09-01');
        $existingEndAt = new \DateTimeImmutable('2023-08-31');
        $existingContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => $startAt,
            'endAt' => $endAt,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'createdAt' => new \DateTimeImmutable('2023-06-06'),
            'contracts' => [$existingContract],
        ]);

        $this->assertSame(1, Factory\ContractFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => 'My contract',
                'maxHours' => 10,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'associateTickets' => true,
            ],
        ]);

        $this->assertSame(2, Factory\ContractFactory::count());
        $contract = Factory\ContractFactory::last();
        $contract->_refresh();
        $this->assertResponseRedirects("/contracts/{$contract->getUid()}", 302);
        $tickets = $contract->getTickets();
        $this->assertSame(0, count($tickets));
    }

    public function testPostNewCanAttachUnaccountedTimeSpentsToContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $startAt = new \DateTimeImmutable('2023-01-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $timeAccountingUnit = 30;
        $ticket = Factory\TicketFactory::createOne([
            'organization' => $organization,
            'createdAt' => new \DateTimeImmutable('2023-06-06'),
        ]);
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => null,
            'time' => 10,
            'realTime' => 10,
        ]);

        $this->assertSame(0, Factory\ContractFactory::count());

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/contracts/new", [
            'contract' => [
                '_token' => $this->generateCsrfToken($client, 'contract'),
                'name' => 'My contract',
                'maxHours' => 10,
                'startAt' => $startAt->format('Y-m-d'),
                'endAt' => $endAt->format('Y-m-d'),
                'timeAccountingUnit' => $timeAccountingUnit,
                'associateTickets' => true,
                'associateUnaccountedTimes' => true,
            ],
        ]);

        $this->assertSame(1, Factory\ContractFactory::count());
        $contract = Factory\ContractFactory::last();
        $this->assertResponseRedirects("/contracts/{$contract->getUid()}", 302);
        $timeSpent->_refresh();
        $timeSpentContract = $timeSpent->getContract();
        $this->assertNotNull($timeSpentContract);
        $this->assertSame($contract->getUid(), $timeSpentContract->getUid());
        $this->assertSame(30, $timeSpent->getTime());
    }

    public function testPostNewCanRenewAContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $renewedContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
        ]);
        $name = 'My contract';
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $timeAccountingUnit = 30;
        $notes = 'Some notes';

        $this->assertSame(1, Factory\ContractFactory::count());

        $client->request(
            Request::METHOD_POST,
            "/organizations/{$organization->getUid()}/contracts/new?from={$renewedContract->getUid()}",
            [
                'contract' => [
                    '_token' => $this->generateCsrfToken($client, 'contract'),
                    'name' => $name,
                    'maxHours' => $maxHours,
                    'startAt' => $startAt->format('Y-m-d'),
                    'endAt' => $endAt->format('Y-m-d'),
                    'timeAccountingUnit' => $timeAccountingUnit,
                    'notes' => $notes,
                ],
            ]
        );

        $this->assertSame(2, Factory\ContractFactory::count());
        $newContract = Factory\ContractFactory::last();
        $this->assertResponseRedirects("/contracts/{$newContract->getUid()}", 302);
        $renewedContract->_refresh();
        $this->assertSame($name, $newContract->getName());
        $this->assertSame($newContract->getId(), $renewedContract->getRenewedBy()?->getId());
    }

    public function testPostNewFailsIfNameIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = str_repeat('a', 256);
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $notes = 'Some notes';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/contracts/new", [
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
        $this->assertSame(0, Factory\ContractFactory::count());
    }

    public function testPostNewFailsIfDatesAreInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = str_repeat('a', 256);
        $maxHours = 10;
        $endAt = new \DateTimeImmutable('2023-12-31');
        $notes = 'Some notes';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/contracts/new", [
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
        $this->assertSame(0, Factory\ContractFactory::count());
    }

    public function testPostNewFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $organization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), [
            'orga:see:contracts',
            'orga:manage:contracts',
        ]);
        $name = 'My contract';
        $maxHours = 10;
        $startAt = new \DateTimeImmutable('2023-09-01');
        $endAt = new \DateTimeImmutable('2023-12-31');
        $notes = 'Some notes';

        $client->request(Request::METHOD_POST, "/organizations/{$organization->getUid()}/contracts/new", [
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
        $this->assertSame(0, Factory\ContractFactory::count());
    }
}
