<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
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
        $this->grantOrga($user->object(), ['orga:update:tickets:contracts']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/contracts/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the contract');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:contracts']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $otherUser,
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:contracts']);
        $organization = OrganizationFactory::createOne();
        $oldContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($newContract->getId(), $contracts[0]->getId());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:contracts']);
        $organization = OrganizationFactory::createOne();
        $oldContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => 'not the token',
            'ongoingContractUid' => $newContract->getUid(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $ticket->refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($oldContract->getId(), $contracts[0]->getId());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $organization = OrganizationFactory::createOne();
        $oldContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:contracts']);
        $organization = OrganizationFactory::createOne();
        $oldContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $newContract = ContractFactory::createOne([
            'organization' => $organization,
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
        ]);
        $ticket = TicketFactory::createOne([
            'organization' => $organization,
            'createdBy' => $otherUser,
            'contracts' => [$oldContract],
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
        ]);
    }
}
