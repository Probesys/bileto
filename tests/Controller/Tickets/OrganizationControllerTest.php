<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Entity;
use App\Repository;
use App\Tests;
use App\Tests\Factory;
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
