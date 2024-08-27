<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Entity\EntityEvent;
use App\Entity\Ticket;
use App\Repository\EntityEventRepository;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\TimeSpentFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $otherUser = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $otherUser,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/contracts/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
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
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($newContract->getId(), $contracts[0]->getId());
    }

    public function testPostUpdateLogsAnEntityEvent(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var EntityEventRepository */
        $entityEventRepository = $entityManager->getRepository(EntityEvent::class);
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
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
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
        ]);

        $entityEvent = $entityEventRepository->findOneBy([
            'type' => 'update',
            'entityType' => Ticket::class,
            'entityId' => $ticket->getId(),
        ]);
        $this->assertNotNull($entityEvent);
        $changes = $entityEvent->getChanges();
        $this->assertSame($oldContract->getId(), $changes['ongoingContract'][0]);
        $this->assertSame($newContract->getId(), $changes['ongoingContract'][1]);
    }

    public function testPostUpdateCanIncludeUnaccountedTimeSpent(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
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
            'timeAccountingUnit' => 30,
        ]);
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);
        $timeSpentAccounted = TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => $oldContract,
        ]);
        $timeSpentNotAccounted = TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => null,
            'time' => 15,
            'realTime' => 15,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
            'includeUnaccountedTime' => true,
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

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
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
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => 'not the token',
            'ongoingContractUid' => $newContract->getUid(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $ticket->_refresh();
        $contracts = $ticket->getContracts();
        $this->assertSame(1, count($contracts));
        $this->assertSame($oldContract->getId(), $contracts[0]->getId());
    }

    public function testPostUpdateFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
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
            'status' => 'closed',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
        ]);
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
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
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $user,
            'contracts' => [$oldContract],
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
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
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:contracts']);
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
            'status' => 'in_progress',
            'organization' => $organization,
            'createdBy' => $otherUser,
            'contracts' => [$oldContract],
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/contracts/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket contracts'),
            'ongoingContractUid' => $newContract->getUid(),
        ]);
    }
}
