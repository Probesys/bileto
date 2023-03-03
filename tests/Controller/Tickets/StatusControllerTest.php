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

class StatusControllerTest extends WebTestCase
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
        $this->grantOrga($user->object(), ['orga:update:tickets:status']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/status/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the status');
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
        $client->request('GET', "/tickets/{$ticket->getUid()}/status/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:status']);
        $oldStatus = 'new';
        $newStatus = 'in_progress';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $oldStatus,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/status/edit");
        $crawler = $client->submitForm('form-update-status-submit', [
            'status' => $newStatus,
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($newStatus, $ticket->getStatus());
    }

    public function testPostUpdateFailsIfStatusIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:status']);
        $oldStatus = 'new';
        $newStatus = 'invalid';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $oldStatus,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/status/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket status'),
            'status' => $newStatus,
        ]);

        $this->assertSelectorTextContains('#status-error', 'The status "invalid" is not a valid status.');
        $ticket->refresh();
        $this->assertSame($oldStatus, $ticket->getStatus());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:status']);
        $oldStatus = 'new';
        $newStatus = 'in_progress';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $oldStatus,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/status/edit", [
            '_csrf_token' => 'not the token',
            'status' => $newStatus,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $ticket->refresh();
        $this->assertSame($oldStatus, $ticket->getStatus());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $oldStatus = 'new';
        $newStatus = 'in_progress';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'status' => $oldStatus,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/status/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket status'),
            'status' => $newStatus,
        ]);
    }
}
