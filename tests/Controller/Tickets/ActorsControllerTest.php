<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Tests\AuthorizationHelper;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ActorsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
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
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->request('GET', "/tickets/{$ticket->getUid()}/actors/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the actors');
    }

    public function testGetEditFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

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

        $client->catchExceptions(false);
        $client->request('GET', "/tickets/{$ticket->getUid()}/actors/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'requester' => $requester,
            'assignee' => $assignee,
        ]);

        $client->catchExceptions(false);
        $client->request('GET', "/tickets/{$ticket->getUid()}/actors/edit");
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
        $team = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $this->grantTeam($team->object(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'team' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'teamUid' => $team->getUid(),
            'assigneeUid' => $assignee->getUid(),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertSame($team->getUid(), $ticket->getTeam()->getUid());
        $this->assertSame($assignee->getUid(), $ticket->getAssignee()->getUid());
    }

    public function testPostUpdateAcceptsEmptyAssignee(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'assigneeUid' => '',
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateSetsNullIfAssigneeIsNotAgent(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $this->grantOrga($assignee->object(), ['orga:create:tickets'], type: 'user');
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => $user,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'assigneeUid' => $assignee->getUid(),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateSetsNullIfTeamNotAuthorizedInOrga(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $initialTeam = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $newTeam = TeamFactory::createOne([
            'agents' => [$assignee],
        ]);
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $this->grantTeam($initialTeam->object(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'team' => $initialTeam,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'teamUid' => $newTeam->getUid(),
            'assigneeUid' => $assignee->getUid(),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertNull($ticket->getTeam());
        $this->assertSame($assignee->getUid(), $ticket->getAssignee()->getUid());
    }

    public function testPostUpdateSetsNullIfAgentIsNotInTeam(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $team = TeamFactory::createOne();
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $this->grantOrga($assignee->object(), ['orga:create:tickets']);
        $this->grantTeam($team->object(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'team' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'teamUid' => $team->getUid(),
            'assigneeUid' => $assignee->getUid(),
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->refresh();
        $this->assertSame($requester->getUid(), $ticket->getRequester()->getUid());
        $this->assertSame($team->getUid(), $ticket->getTeam()->getUid());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfRequesterIsNotInOrganization(): void
    {
        $client = static::createClient();
        list(
            $user,
            $requester,
        ) = UserFactory::createMany(2);
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'assigneeUid' => $user->getUid(),
        ]);

        $this->assertSelectorTextContains('#requester-error', 'Select a user from the list');
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
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $this->grantOrga($assignee->object(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => 'not the token',
            'requesterUid' => $requester->getUid(),
            'assigneeUid' => $assignee->getUid(),
        ]);

        $this->assertSelectorTextContains('[data-test="alert-error"]', 'The security token is invalid');
        $ticket->refresh();
        $this->assertNull($ticket->getRequester());
        $this->assertNull($ticket->getAssignee());
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $this->grantOrga($assignee->object(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'createdBy' => $user,
            'requester' => null,
            'assignee' => null,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'assigneeUid' => $assignee->getUid(),
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        list(
            $user,
            $requester,
            $assignee,
        ) = UserFactory::createMany(3);
        $client->loginUser($user->object());
        $this->grantOrga($user->object(), ['orga:update:tickets:actors']);
        $this->grantOrga($requester->object(), ['orga:create:tickets']);
        $this->grantOrga($assignee->object(), ['orga:create:tickets']);
        $ticket = TicketFactory::createOne([
            'requester' => null,
            'assignee' => null,
        ]);

        $client->catchExceptions(false);
        $client->request('POST', "/tickets/{$ticket->getUid()}/actors/edit", [
            '_csrf_token' => $this->generateCsrfToken($client, 'update ticket actors'),
            'requesterUid' => $requester->getUid(),
            'assigneeUid' => $assignee->getUid(),
        ]);
    }
}
