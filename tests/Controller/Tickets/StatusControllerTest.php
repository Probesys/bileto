<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StatusControllerTest extends WebTestCase
{
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;
    use Factories;
    use ResetDatabase;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the status');
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");
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
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");
    }

    public function testPostEditSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $oldStatus = 'new';
        $newStatus = 'in_progress';
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $oldStatus,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/edit", [
            'ticket_status' => [
                '_token' => $this->generateCsrfToken($client, 'ticket status'),
                'status' => $newStatus,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newStatus, $ticket->getStatus());
    }

    public function testPostEditFailsIfStatusIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $oldStatus = 'new';
        $newStatus = 'invalid';
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $oldStatus,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/edit", [
            'ticket_status' => [
                '_token' => $this->generateCsrfToken($client, 'ticket status'),
                'status' => $newStatus,
            ],
        ]);

        $this->assertSelectorTextContains('#ticket_status_status-error', 'The selected choice is invalid');
        $this->clearEntityManager();
        $ticket->_refresh();
        $this->assertSame($oldStatus, $ticket->getStatus());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $oldStatus = 'new';
        $newStatus = 'in_progress';
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $oldStatus,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/edit", [
            'ticket_status' => [
                '_token' => 'not the token',
                'status' => $newStatus,
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_status-error', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertSame($oldStatus, $ticket->getStatus());
    }

    public function testPostReopenSetsStatusToNewIfUnassigned(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'closed',
            'assignee' => null,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/reopening", [
            '_csrf_token' => $this->generateCsrfToken($client, 'reopen ticket'),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame('new', $ticket->getStatus());
    }

    public function testPostReopenSetsStatusToInProgressIfAssigned(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'closed',
            'assignee' => Factory\UserFactory::createOne(),
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/reopening", [
            '_csrf_token' => $this->generateCsrfToken($client, 'reopen ticket'),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame('in_progress', $ticket->getStatus());
    }

    public function testPostReopenFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'closed',
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/reopening", [
            '_csrf_token' => 'not a token',
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertSame('closed', $ticket->getStatus());
    }

    public function testPostReopenFailsIfTicketIsNotClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'resolved',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/reopening", [
            '_csrf_token' => $this->generateCsrfToken($client, 'reopen ticket'),
        ]);
    }

    public function testPostReopenFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
            'createdBy' => $user,
            'status' => 'closed',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/reopening", [
            '_csrf_token' => $this->generateCsrfToken($client, 'reopen ticket'),
        ]);
    }

    public function testPostReopenFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:status']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/reopening", [
            '_csrf_token' => $this->generateCsrfToken($client, 'reopen ticket'),
        ]);
    }
}
