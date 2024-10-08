<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\AuthorizationHelper;
use App\Tests\FactoriesHelper;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PriorityControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use FactoriesHelper;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
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
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/priority/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/priority/edit");
        $crawler = $client->submitForm('form-update-priority-submit', [
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newUrgency, $ticket->getUrgency());
        $this->assertSame($newImpact, $ticket->getImpact());
        $this->assertSame($newPriority, $ticket->getPriority());
    }

    public function testPostUpdateFailsIfPriorityIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'low';
        $newImpact = 'high';
        $newPriority = 'invalid';
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket priority'),
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);

        $this->assertSelectorTextContains('#priority-error', 'Select a priority from the list');
        $this->clearEntityManager();
        $ticket->_refresh();
        $this->assertSame($oldUrgency, $ticket->getUrgency());
        $this->assertSame($oldImpact, $ticket->getImpact());
        $this->assertSame($oldPriority, $ticket->getPriority());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => 'not the token',
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertSame($oldUrgency, $ticket->getUrgency());
        $this->assertSame($oldImpact, $ticket->getImpact());
        $this->assertSame($oldPriority, $ticket->getPriority());
    }

    public function testPostUpdateFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket priority'),
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket priority'),
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'status' => 'in_progress',
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket priority'),
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);
    }
}
