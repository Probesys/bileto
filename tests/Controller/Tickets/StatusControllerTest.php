<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
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

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");

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
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:status']);
        $ticket = TicketFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");
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

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/status/edit");
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

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket status'),
            'status' => $newStatus,
        ]);

        $this->assertSelectorTextContains('#status-error', 'Select a status from the list');
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

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/edit", [
            '_csrf_token' => 'not the token',
            'status' => $newStatus,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
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
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket status'),
            'status' => $newStatus,
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:status']);
        $oldStatus = 'new';
        $newStatus = 'in_progress';
        $ticket = TicketFactory::createOne([
            'status' => $oldStatus,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/status/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket status'),
            'status' => $newStatus,
        ]);
    }
}
