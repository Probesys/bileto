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

class TypeControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:type']);
        $oldType = 'request';
        $newType = 'incident';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'type' => $oldType,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}");
        $crawler = $client->submitForm('form-update-type-submit');

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($newType, $ticket->getType());
    }

    public function testPostUpdateFailsIfTypeIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:type']);
        $oldType = 'request';
        $newType = 'not a type';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'type' => $oldType,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/type/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket type'),
            'type' => $newType,
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'Select a type from the list');
        $ticket->refresh();
        $this->assertSame($oldType, $ticket->getType());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:type']);
        $oldType = 'request';
        $newType = 'incident';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'type' => $oldType,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/type/edit", [
            '_csrf_token' => 'not the token',
            'type' => $newType,
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        $ticket->refresh();
        $this->assertSame($oldType, $ticket->getType());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $oldType = 'request';
        $newType = 'incident';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'type' => $oldType,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/type/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket type'),
            'type' => $newType,
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:type']);
        $oldType = 'request';
        $newType = 'incident';
        $ticket = TicketFactory::createOne([
            'type' => $oldType,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/type/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket type'),
            'type' => $newType,
        ]);
    }
}
