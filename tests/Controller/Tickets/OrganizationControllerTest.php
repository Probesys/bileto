<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Entity;
use App\Repository;
use App\Tests;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class OrganizationControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/organization/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Transfer the ticket');
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/organization/edit");
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
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/organization/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $otherUser,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/organization/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'organization' => $oldOrganization,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => $this->generateCsrfToken($client, 'ticket organization'),
                'organization' => $newOrganization->getId(),
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newOrganization->getId(), $ticket->getOrganization()->getId());
    }

    public function testPostUpdateChangesActors(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $requester = Factory\UserFactory::createOne();
        $oldObserver = Factory\UserFactory::createOne();
        $newObserver = Factory\UserFactory::createOne();
        $team = Factory\TeamFactory::createOne();
        $assignee = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne([
            'observers' => [$newObserver],
        ]);
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $this->grantOrga($requester->_real(), ['orga:see'], $oldOrganization->_real());
        $this->grantOrga($oldObserver->_real(), ['orga:see'], $oldOrganization->_real());
        $this->grantOrga($newObserver->_real(), ['orga:see'], $newOrganization->_real());
        $this->grantOrga($assignee->_real(), ['orga:see'], $oldOrganization->_real());
        $this->grantTeam($team->_real(), ['orga:see'], $oldOrganization->_real());
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'organization' => $oldOrganization,
            'requester' => $requester,
            'team' => $team,
            'assignee' => $assignee,
            'observers' => [$oldObserver],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => $this->generateCsrfToken($client, 'ticket organization'),
                'organization' => $newOrganization->getId(),
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newOrganization->getId(), $ticket->getOrganization()->getId());
        // The requester is not changed even if they don't have access to the
        // new organization. This is because ticket's requester cannot be null
        // and we cannot determine a different requester. It's also more
        // logical. However, the requester may not have access to the ticket.
        $this->assertSame($requester->getId(), $ticket->getRequester()->getId());
        $this->assertNull($ticket->getTeam());
        $this->assertNull($ticket->getAssignee());
        $ticketObservers = $ticket->getObservers();
        $this->assertSame(1, count($ticketObservers));
        $this->assertSame($newObserver->getId(), $ticketObservers[0]->getId());
    }

    public function testPostUpdateChangesContracts(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne();
        $oldContract = Factory\ContractFactory::createOne([
            'organization' => $oldOrganization,
            'timeAccountingUnit' => 30,
        ]);
        $newContract = Factory\ContractFactory::createOne([
            'organization' => $newOrganization,
            'maxHours' => 42,
            'timeAccountingUnit' => 20,
            'startAt' => Utils\Time::ago(1, 'month'),
            'endAt' => Utils\Time::fromNow(1, 'month'),
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'organization' => $oldOrganization,
            'contracts' => [$oldContract],
        ]);
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => $oldContract,
            'time' => 30,
            'realTime' => 10,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => $this->generateCsrfToken($client, 'ticket organization'),
                'organization' => $newOrganization->getId(),
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newOrganization->getId(), $ticket->getOrganization()->getId());
        $ticketContracts = $ticket->getContracts();
        $this->assertSame(1, count($ticketContracts));
        $this->assertSame($newContract->getId(), $ticketContracts[0]->getId());
        // For now, the times spent are not accounted for the new ongoing
        // contract to let the commercials decide if they want to or not. This
        // may change in the future.
        $timeSpent->_refresh();
        $this->assertNull($timeSpent->getContract());
        $this->assertSame(10, $timeSpent->getTime());
    }

    public function testPostUpdateFailsIfAccessIsForbiddenInDestinationOrganization(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne();
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization'], $oldOrganization->_real());
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'organization' => $oldOrganization,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => $this->generateCsrfToken($client, 'ticket organization'),
                'organization' => $newOrganization->getId(),
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_organization_organization-error', 'The selected choice is invalid');
        $ticket->_refresh();
        $this->assertSame($oldOrganization->getId(), $ticket->getOrganization()->getId());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'organization' => $oldOrganization,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => 'not a token',
                'organization' => $newOrganization->getId(),
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_organization-error', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertSame($oldOrganization->getId(), $ticket->getOrganization()->getId());
    }

    public function testPostUpdateFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:organization']);
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
            'organization' => $oldOrganization,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => $this->generateCsrfToken($client, 'ticket organization'),
                'organization' => $newOrganization->getId(),
            ],
        ]);
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'organization' => $oldOrganization,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => $this->generateCsrfToken($client, 'ticket organization'),
                'organization' => $newOrganization->getId(),
            ],
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $oldOrganization = Factory\OrganizationFactory::createOne();
        $newOrganization = Factory\OrganizationFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $otherUser,
            'organization' => $oldOrganization,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/organization/edit", [
            'ticket_organization' => [
                '_token' => $this->generateCsrfToken($client, 'ticket organization'),
                'organization' => $newOrganization->getId(),
            ],
        ]);
    }
}
