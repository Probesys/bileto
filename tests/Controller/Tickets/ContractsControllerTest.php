<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

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

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the contract');
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $otherUser,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testPostEditSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => $newContract->getId(),
                'includeUnaccountedTime' => false,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($newContract->getId(), $contracts[0]->getId());
    }

    public function testPostEditCanUnassignContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => '',
                'includeUnaccountedTime' => false,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(0, count($contracts));
    }

    public function testPostEditCanIncludeUnaccountedTimeSpent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
            'timeAccountingUnit' => 30,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);
        $timeSpentAccounted = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => $oldContract,
        ]);
        $timeSpentNotAccounted = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => null,
            'time' => 15,
            'realTime' => 15,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => $this->generateCsrfToken($client, 'ticket contract'),
                'ongoingContract' => $newContract->getId(),
                'includeUnaccountedTime' => true,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($newContract->getId(), $contracts[0]->getId());
        $timeSpentAccounted->_refresh();
        $this->assertSame($oldContract->getId(), $timeSpentAccounted->getContract()->getId());
        $timeSpentNotAccounted->_refresh();
        $this->assertSame($newContract->getId(), $timeSpentNotAccounted->getContract()->getId());
        $this->assertSame(30, $timeSpentNotAccounted->getTime());
        $this->assertSame(15, $timeSpentNotAccounted->getRealTime());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $organization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = Factory\ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            'ticket_ongoing_contract' => [
                '_token' => 'not the token',
                'ongoingContract' => $newContract->getId(),
            ],
        ]);

        $this->assertSelectorTextContains('#ticket_ongoing_contract-error', 'The security token is invalid');
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($oldContract->getId(), $contracts[0]->getId());
    }
}
