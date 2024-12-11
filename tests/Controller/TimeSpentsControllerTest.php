<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller;

use App\Tests;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TimeSpentsControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:time_spent']);
        $ticket = Factory\TicketFactory::createOne([
            'requester' => $user,
            'status' => 'in_progress',
        ]);
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
        ]);

        $client->request(Request::METHOD_GET, "/time-spents/{$timeSpent->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit time spent');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $ticket = Factory\TicketFactory::createOne([
            'requester' => $user,
            'status' => 'in_progress',
        ]);
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/time-spents/{$timeSpent->getUid()}/edit");
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:time_spent']);
        $ticket = Factory\TicketFactory::createOne([
            'requester' => $user,
            'status' => 'closed',
        ]);
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/time-spents/{$timeSpent->getUid()}/edit");
    }

    public function testPostEditSavesTheTimeSpent(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:time_spent']);
        $ticket = Factory\TicketFactory::createOne([
            'requester' => $user,
            'status' => 'in_progress',
        ]);
        $oldRealTime = 5;
        $newRealTime = 15;
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => $oldRealTime,
        ]);

        $client->request(Request::METHOD_POST, "/time-spents/{$timeSpent->getUid()}/edit", [
            'time_spent' => [
                '_token' => $this->generateCsrfToken($client, 'time_spent'),
                'realTime' => $newRealTime,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $timeSpent->_refresh();
        $this->assertSame($newRealTime, $timeSpent->getRealTime());
    }

    public function testPostEditReaccountsTimeOnContract(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:time_spent']);
        $contract = Factory\ContractFactory::createOne([
            'startAt' => Utils\Time::ago(1, 'week'),
            'endAt' => Utils\Time::fromNow(1, 'week'),
            'timeAccountingUnit' => 10,
        ]);
        $ticket = Factory\TicketFactory::createOne([
            'requester' => $user,
            'status' => 'in_progress',
            'contracts' => [$contract],
        ]);
        $oldRealTime = 5;
        $oldTime = 10;
        $newRealTime = 15;
        $newTime = 20;
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'contract' => $contract,
            'realTime' => $oldRealTime,
            'time' => $oldTime,
        ]);

        $client->request(Request::METHOD_POST, "/time-spents/{$timeSpent->getUid()}/edit", [
            'time_spent' => [
                '_token' => $this->generateCsrfToken($client, 'time_spent'),
                'realTime' => $newRealTime,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $timeSpent->_refresh();
        $this->assertSame($newRealTime, $timeSpent->getRealTime());
        $this->assertSame($newTime, $timeSpent->getTime());
        $this->assertSame($contract->getId(), $timeSpent->getContract()?->getId());
    }

    public function testPostEditDeletesTimeSpentIfTimeIsZero(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:time_spent']);
        $ticket = Factory\TicketFactory::createOne([
            'requester' => $user,
            'status' => 'in_progress',
        ]);
        $oldRealTime = 5;
        $newRealTime = 0;
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => $oldRealTime,
        ]);

        $client->request(Request::METHOD_POST, "/time-spents/{$timeSpent->getUid()}/edit", [
            'time_spent' => [
                '_token' => $this->generateCsrfToken($client, 'time_spent'),
                'realTime' => $newRealTime,
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        Factory\TimeSpentFactory::assert()->notExists(['id' => $timeSpent->getId()]);
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:create:tickets:time_spent']);
        $ticket = Factory\TicketFactory::createOne([
            'requester' => $user,
            'status' => 'in_progress',
        ]);
        $oldRealTime = 5;
        $newRealTime = 15;
        $timeSpent = Factory\TimeSpentFactory::createOne([
            'ticket' => $ticket,
            'realTime' => $oldRealTime,
        ]);

        $client->request(Request::METHOD_POST, "/time-spents/{$timeSpent->getUid()}/edit", [
            'time_spent' => [
                '_token' => 'not a token',
                'realTime' => $newRealTime,
            ],
        ]);

        $this->assertSelectorTextContains('#time_spent-error', 'The security token is invalid');
        $this->clearEntityManager();
        $timeSpent->_refresh();
        $this->assertSame($oldRealTime, $timeSpent->getRealTime());
    }
}
