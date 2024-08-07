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

class TitleControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Rename the ticket');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $ticket = TicketFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $oldTitle = 'My ticket';
        $newTitle = 'My urgent ticket!';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'title' => $oldTitle,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/title/edit");
        $crawler = $client->submitForm('form-update-title-submit', [
            'title' => $newTitle,
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $this->assertSame($newTitle, $ticket->getTitle());
    }

    public function testPostUpdateFailsIfTitleIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $oldTitle = 'My ticket';
        $newTitle = str_repeat('a', 256);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'title' => $oldTitle,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/title/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket title'),
            'title' => $newTitle,
        ]);

        $ticket->_refresh();
        $this->assertSelectorTextContains('#title-error', 'Enter a title of less than 255 characters');
        $this->assertSame($oldTitle, $ticket->getTitle());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $oldTitle = 'My ticket';
        $newTitle = 'My urgent ticket!';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'title' => $oldTitle,
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/title/edit", [
            '_csrf_token' => 'not the token',
            'title' => $newTitle,
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $ticket->_refresh();
        $this->assertSame($oldTitle, $ticket->getTitle());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $oldTitle = 'My ticket';
        $newTitle = 'My urgent ticket!';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'title' => $oldTitle,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/title/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket title'),
            'title' => $newTitle,
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:title']);
        $oldTitle = 'My ticket';
        $newTitle = 'My urgent ticket!';
        $ticket = TicketFactory::createOne([
            'title' => $oldTitle,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/title/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket title'),
            'title' => $newTitle,
        ]);
    }
}
