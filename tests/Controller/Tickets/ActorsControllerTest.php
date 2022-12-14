<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ActorsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/actors/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the actors');
    }

    public function testGetEditRedirectsToLoginIfNotConnected(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/actors/edit");

        $this->assertResponseRedirects('http://localhost/login', 302);
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/actors/edit");
        $crawler = $client->submitForm('form-update-actors-submit', [
            'requesterId' => $requester->getId(),
            'assigneeId' => $assignee->getId(),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($requester->getId(), $ticket->getRequester()->getId());
        $this->assertSame($assignee->getId(), $ticket->getAssignee()->getId());
    }

    public function testPostUpdateAcceptsEmptyAssignee(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterId' => $requester->getId(),
            'assigneeId' => '',
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($requester->getId(), $ticket->getRequester()->getId());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfRequesterIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterId' => -1,
            'assigneeId' => $user->getId(),
        ]);

        $this->assertSelectorTextContains('#requester-error', 'The requester must exist.');
        $ticket->refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfAssigneeIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterId' => $user->getId(),
            'assigneeId' => -1,
        ]);

        $this->assertSelectorTextContains('#assignee-error', 'The assignee must exist.');
        $ticket->refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => 'not the token',
            'requesterId' => $requester->getId(),
            'assigneeId' => $assignee->getId(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'Invalid CSRF token.');
        $ticket->refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
    }
}
