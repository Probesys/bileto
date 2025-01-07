<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests;
use App\Tests\Factory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PriorityControllerTest extends WebTestCase
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
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/priority/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the priority');
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/priority/edit");
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
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/priority/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/priority/edit");
    }

    public function testPostEditSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            'ticket_priority' => [
                '_token' => $this->generateCsrfToken($client, 'ticket priority'),
                'urgency' => $newUrgency,
                'impact' => $newImpact,
                'priority' => $newPriority,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newUrgency, $ticket->getUrgency());
        $this->assertSame($newImpact, $ticket->getImpact());
        $this->assertSame($newPriority, $ticket->getPriority());
    }

    public function testPostEditFailsIfPriorityIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'low';
        $newImpact = 'high';
        $newPriority = 'invalid';
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            'ticket_priority' => [
                '_token' => $this->generateCsrfToken($client, 'ticket priority'),
                'urgency' => $newUrgency,
                'impact' => $newImpact,
                'priority' => $newPriority,
            ],
        ]);

        $this->assertSelectorTextContains('#ticket_priority_priority-error', 'The selected choice is invalid');
        $this->clearEntityManager();
        $ticket->_refresh();
        $this->assertSame($oldUrgency, $ticket->getUrgency());
        $this->assertSame($oldImpact, $ticket->getImpact());
        $this->assertSame($oldPriority, $ticket->getPriority());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            'ticket_priority' => [
                '_token' => 'not the token',
                'urgency' => $newUrgency,
                'impact' => $newImpact,
                'priority' => $newPriority,
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_priority-error', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertSame($oldUrgency, $ticket->getUrgency());
        $this->assertSame($oldImpact, $ticket->getImpact());
        $this->assertSame($oldPriority, $ticket->getPriority());
    }
}
