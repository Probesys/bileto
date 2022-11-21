<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Factory\TicketFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TypeControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $oldType = 'request';
        $newType = 'incident';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'type' => $oldType,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");
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
        $oldType = 'request';
        $newType = 'not a type';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'type' => $oldType,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");
        $crawler = $client->submitForm('form-update-type-submit', [
            'type' => $newType,
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($oldType, $ticket->getType());
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user->object());
        $oldType = 'request';
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'type' => $oldType,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}");
        $crawler = $client->submitForm('form-update-type-submit', [
            '_csrf_token' => 'not the token',
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($oldType, $ticket->getType());
    }
}
