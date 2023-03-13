<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PriorityControllerTest extends WebTestCase
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
        $this->grantOrga($user->object(), ['orga:update:tickets:priority']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/priority/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the priority');
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
        $client->request('GET', "/tickets/{$ticket->getUid()}/priority/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:priority']);
        $ticket = TicketFactory::createOne();

        $client->catchExceptions(false);
        $client->request('GET', "/tickets/{$ticket->getUid()}/priority/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/priority/edit");
        $crawler = $client->submitForm('form-update-priority-submit', [
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($newUrgency, $ticket->getUrgency());
        $this->assertSame($newImpact, $ticket->getImpact());
        $this->assertSame($newPriority, $ticket->getPriority());
    }

    public function testPostUpdateFailsIfPriorityIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'low';
        $newImpact = 'high';
        $newPriority = 'invalid';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket priority'),
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);

        $this->assertSelectorTextContains('#priority-error', 'Select a priority from the list');
        $ticket->refresh();
        $this->assertSame($oldUrgency, $ticket->getUrgency());
        $this->assertSame($oldImpact, $ticket->getImpact());
        $this->assertSame($oldPriority, $ticket->getPriority());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => 'not the token',
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $ticket->refresh();
        $this->assertSame($oldUrgency, $ticket->getUrgency());
        $this->assertSame($oldImpact, $ticket->getImpact());
        $this->assertSame($oldPriority, $ticket->getPriority());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/priority/edit", [
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
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:priority']);
        $oldUrgency = 'low';
        $oldImpact = 'low';
        $oldPriority = 'low';
        $newUrgency = 'high';
        $newImpact = 'high';
        $newPriority = 'high';
        $ticket = TicketFactory::createOne([
            'urgency' => $oldUrgency,
            'impact' => $oldImpact,
            'priority' => $oldPriority,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/priority/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket priority'),
            'urgency' => $newUrgency,
            'impact' => $newImpact,
            'priority' => $newPriority,
        ]);
    }
}
