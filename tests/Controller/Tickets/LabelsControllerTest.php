<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Controller\Tickets;

use App\Entity;
use App\Repository;
use App\Tests\AuthorizationHelper;
use App\Tests\Factory;
use App\Tests\SessionHelper;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LabelsControllerTest extends WebTestCase
{
    use AuthorizationHelper;
    use Factories;
    use ResetDatabase;
    use SessionHelper;

    public function testGetEditRendersCorrectly(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
        ]);

        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/labels/edit");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Edit the labels');
    }

    public function testGetEditFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/labels/edit");
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
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/labels/edit");
    }

    public function testGetEditFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $otherUser,
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_GET, "/tickets/{$ticket->getUid()}/labels/edit");
    }

    public function testPostUpdateSavesTicketAndRedirects(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $oldLabel = Factory\LabelFactory::createOne();
        $newLabel = Factory\LabelFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'labels' => [$oldLabel],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/labels/edit", [
            'ticket_labels' => [
                '_token' => $this->generateCsrfToken($client, 'ticket labels'),
                'labels' => [$newLabel->getId()],
            ],
        ]);

        $this->assertResponseRedirects("/tickets/{$ticket->getUid()}", 302);
        $ticket->_refresh();
        $labels = $ticket->getLabels();
        $this->assertSame(1, count($labels));
        $this->assertSame($newLabel->getId(), $labels[0]->getId());
    }

    public function testPostUpdateLogsAnEntityEvent(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();
        /** @var Repository\EntityEventRepository */
        $entityEventRepository = $entityManager->getRepository(Entity\EntityEvent::class);
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $oldLabel = Factory\LabelFactory::createOne();
        $newLabel = Factory\LabelFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'labels' => [$oldLabel],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/labels/edit", [
            'ticket_labels' => [
                '_token' => $this->generateCsrfToken($client, 'ticket labels'),
                'labels' => [$newLabel->getId()],
            ],
        ]);

        $entityEvent = $entityEventRepository->findOneBy([
            'type' => 'update',
            'entityType' => Entity\Ticket::class,
            'entityId' => $ticket->getId(),
        ]);
        $this->assertNotNull($entityEvent);
        $changes = $entityEvent->getChanges();
        $this->assertSame([$oldLabel->getId()], $changes['labels'][0]);
        $this->assertSame([$newLabel->getId()], $changes['labels'][1]);
    }

    public function testPostUpdateFailsIfCsrfTokenIsInvalid(): void
    {
        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $oldLabel = Factory\LabelFactory::createOne();
        $newLabel = Factory\LabelFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'labels' => [$oldLabel],
        ]);

        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/labels/edit", [
            'ticket_labels' => [
                '_token' => 'not a token',
                'labels' => [$newLabel->getId()],
            ],
        ]);

        $this->assertSelectorTextContains('#ticket_labels-error', 'The security token is invalid');
        $ticket->_refresh();
        $labels = $ticket->getLabels();
        $this->assertSame(1, count($labels));
        $this->assertSame($oldLabel->getId(), $labels[0]->getId());
    }

    public function testPostUpdateFailsIfTicketIsClosed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $oldLabel = Factory\LabelFactory::createOne();
        $newLabel = Factory\LabelFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'closed',
            'createdBy' => $user,
            'labels' => [$oldLabel],
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/labels/edit", [
            'ticket_labels' => [
                '_token' => $this->generateCsrfToken($client, 'ticket labels'),
                'labels' => [$newLabel->getId()],
            ],
        ]);
    }

    public function testPostUpdateFailsIfAccessIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $oldLabel = Factory\LabelFactory::createOne();
        $newLabel = Factory\LabelFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $user,
            'labels' => [$oldLabel],
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/labels/edit", [
            'ticket_labels' => [
                '_token' => $this->generateCsrfToken($client, 'ticket labels'),
                'labels' => [$newLabel->getId()],
            ],
        ]);
    }

    public function testPostUpdateFailsIfAccessToTicketIsForbidden(): void
    {
        $this->expectException(AccessDeniedException::class);

        $client = static::createClient();
        $user = Factory\UserFactory::createOne();
        $otherUser = Factory\UserFactory::createOne();
        $client->loginUser($user->_real());
        $this->grantOrga($user->_real(), ['orga:update:tickets:labels']);
        $oldLabel = Factory\LabelFactory::createOne();
        $newLabel = Factory\LabelFactory::createOne();
        $ticket = Factory\TicketFactory::createOne([
            'status' => 'in_progress',
            'createdBy' => $otherUser,
            'labels' => [$oldLabel],
        ]);

        $client->catchExceptions(false);
        $client->request(Request::METHOD_POST, "/tickets/{$ticket->getUid()}/labels/edit", [
            'ticket_labels' => [
                '_token' => $this->generateCsrfToken($client, 'ticket labels'),
                'labels' => [$newLabel->getId()],
            ],
        ]);
    }
}
