<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ActorsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

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

        $client->request('GET', "/tickets/{$ticket->getUid()}/actors/edit");
        $crawler = $client->submitForm('form-update-actors-submit', [
            '_csrf_token' => 'not the token',
            'requesterId' => $requester->getId(),
            'assigneeId' => $assignee->getId(),
        ]);

        $ticket->refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
    }
}
