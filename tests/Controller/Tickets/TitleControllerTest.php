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

class TitleControllerTest extends WebTestCase
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
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Rename the ticket');
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");
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
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");
    }

    public function testPostEditSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $oldTitle = 'My ticket';
        $newTitle = 'My urgent ticket!';
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'title' => $oldTitle,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/title/edit", [
            'ticket_title' => [
                '_token' => $this->generateCsrfToken($client, 'ticket title'),
                'title' => $newTitle,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newTitle, $ticket->getTitle());
    }

    public function testPostEditFailsIfTitleIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $oldTitle = 'My ticket';
        $newTitle = str_repeat('a', 256);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'title' => $oldTitle,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/title/edit", [
            'ticket_title' => [
                '_token' => $this->generateCsrfToken($client, 'ticket title'),
                'title' => $newTitle,
            ],
        ]);

        $this->clearEntityManager();

        $ticket->_refresh();
        $this->assertSelectorTextContains('#ticket_title_title-error', 'Enter a title of less than 255 characters');
        $this->assertSame($oldTitle, $ticket->getTitle());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $oldTitle = 'My ticket';
        $newTitle = 'My urgent ticket!';
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'title' => $oldTitle,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/title/edit", [
            'ticket_title' => [
                '_token' => 'not the token',
                'title' => $newTitle,
            ],
        ]);

        $this->clearEntityManager();

        $this->assertSelectorTextContains('#ticket_title-error', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertSame($oldTitle, $ticket->getTitle());
    }
}
