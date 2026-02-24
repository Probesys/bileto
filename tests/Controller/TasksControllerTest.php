<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
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

class TasksControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;
    use Tests\FactoriesHelper;
    use Tests\SessionHelper;

    public function testGetIcsRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:see:tickets:tasks']);
        $task = Factory\TaskFactory::createOne();

        $client->request(Request::METHOD_GET, "/tasks/{$task->getUid()}.ics");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        $expectedFilename = "task-{$task->getUid()}.ics";
        $this->assertResponseHeaderSame('Content-Disposition', "attachment; filename=\"{$expectedFilename}\"");
    }

    public function testGetIcsFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all']);
        $task = Factory\TaskFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tasks/{$task->getUid()}.ics");
    }

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:create:tickets:tasks']);
        $task = Factory\TaskFactory::createOne();

        $client->request(Request::METHOD_GET, "/tasks/{$task->getUid()}/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the task');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $task = Factory\TaskFactory::createOne();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tasks/{$task->getUid()}/edit");
    }

    public function testPostEditSavesTheTask(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:create:tickets:tasks']);
        $oldLabel = 'My old task';
        $newLabel = 'My new task';
        $task = Factory\TaskFactory::createOne([
            'label' => $oldLabel,
        ]);

        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/edit", [
            'task' => [
                '_token' => $this->generateCsrfToken($client, 'task'),
                'label' => $newLabel,
                'startAt' => '2026-03-25T12:00',
                'endAt' => '2026-03-25T14:00',
            ],
        ]);

        $ticket = $task->getTicket();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $task->_refresh();
        $this->assertSame($newLabel, $task->getLabel());
    }

    public function testPostEditFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:create:tickets:tasks']);
        $oldLabel = 'My old task';
        $newLabel = 'My new task';
        $task = Factory\TaskFactory::createOne([
            'label' => $oldLabel,
        ]);

        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/edit", [
            'task' => [
                '_token' => 'not a token',
                'label' => $newLabel,
                'startAt' => '2026-03-25T12:00',
                'endAt' => '2026-03-25T14:00',
            ],
        ]);

        $this->assertSelectorTextContains('#task-error', 'The security token is invalid');
        $this->clearEntityManager();
        $task->_refresh();
        $this->assertSame($oldLabel, $task->getLabel());
    }

    public function testPostFinishMarksTaskAsFinished(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:create:tickets:tasks']);
        $task = Factory\TaskFactory::new()->unfinished()->create();

        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/finish", [
            '_token' => $this->generateCsrfToken($client, 'finish task'),
        ]);

        $ticket = $task->getTicket();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $task->_refresh();
        $this->assertTrue($task->isFinished());
    }

    public function testPostFinishFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:create:tickets:tasks']);
        $task = Factory\TaskFactory::new()->unfinished()->create();

        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/finish", [
            '_token' => 'not a token',
        ]);

        $ticket = $task->getTicket();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        $task->_refresh();
        $this->assertFalse($task->isFinished());
    }

    public function testPostFinishFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all']);
        $task = Factory\TaskFactory::new()->unfinished()->create();

        $this->clearEntityManager();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/finish", [
            '_token' => $this->generateCsrfToken($client, 'finish task'),
        ]);
    }

    public function testPostDeleteDeletesTheTask(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:create:tickets:tasks']);
        $task = Factory\TaskFactory::createOne();

        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/deletion", [
            '_token' => $this->generateCsrfToken($client, 'delete task'),
        ]);

        $ticket = $task->getTicket();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        Factory\TaskFactory::assert()->notExists(['id' => $task->getId()]);
    }

    public function testPostDeleteFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all', 'orga:create:tickets:tasks']);
        $task = Factory\TaskFactory::createOne();

        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/deletion", [
            '_csrf_token' => 'not the token',
        ]);

        $ticket = $task->getTicket();
        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $client->followRedirect();
        $this->assertSelectorTextContains('#notifications', 'The security token is invalid');
        Factory\TaskFactory::assert()->exists(['id' => $task->getId()]);
    }

    public function testPostDeleteFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:see:tickets:all']);
        $task = Factory\TaskFactory::createOne();

        $this->clearEntityManager();

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tasks/{$task->getUid()}/deletion", [
            '_token' => $this->generateCsrfToken($client, 'delete task'),
        ]);
    }
}
