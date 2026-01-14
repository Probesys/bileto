<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Twig;

use App\Entity\EntityEvent;
use App\Entity\Ticket;
use App\Repository\EntityEventRepository;
use App\Repository\TicketRepository;
use App\Tests\Factory\MessageFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\TicketFactory;
use App\Tests\Factory\UserFactory;
use App\Twig\TicketEventChangesFormatterExtension;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketEventChangesFormatterExtensionTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    private TicketRepository $ticketRepository;

    private EntityEventRepository $entityEventRepository;

    private TicketEventChangesFormatterExtension $formatter;

    #[Before]
    public function setUpTest(): void
    {
        $this->client = static::createClient();

        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var TicketRepository $repository */
        $repository = $entityManager->getRepository(Ticket::class);
        $this->ticketRepository = $repository;

        /** @var EntityEventRepository $repository */
        $repository = $entityManager->getRepository(EntityEvent::class);
        $this->entityEventRepository = $repository;

        /** @var TIcketEventChangesFormatterExtension $formatter */
        $formatter = $container->get(TicketEventChangesFormatterExtension::class);
        $this->formatter = $formatter;

        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());
    }

    /**
     * @param Proxy<Ticket> $ticket
     */
    private function saveEvent(Proxy $ticket): EntityEvent
    {
        $this->ticketRepository->save($ticket->_real(), true);
        return $this->entityEventRepository->findOneBy([
            'type' => 'update',
            'entityType' => Ticket::class,
            'entityId' => $ticket->getId(),
        ]);
    }

    public function testFormatTicketChangesFormatsTitle(): void
    {
        $ticket = TicketFactory::createOne([
            'title' => 'The old title',
        ]);
        $ticket->setTitle('The new title');
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'title');

        $this->assertStringContainsString('renamed the ticket', $message);
    }

    public function testFormatTicketChangesFormatsStatus(): void
    {
        $ticket = TicketFactory::createOne([
            'status' => 'new',
        ]);
        $ticket->setStatus('in_progress');
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'status');

        $this->assertStringContainsString('changed the status', $message);
    }

    public function testFormatTicketChangesFormatsTypeToRequest(): void
    {
        $ticket = TicketFactory::createOne([
            'type' => 'incident',
        ]);
        $ticket->setType('request');
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'type');

        $this->assertStringContainsString('changed the ticket to request', $message);
    }

    public function testFormatTicketChangesFormatsTypeToIncident(): void
    {
        $ticket = TicketFactory::createOne([
            'type' => 'request',
        ]);
        $ticket->setType('incident');
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'type');

        $this->assertStringContainsString('changed the ticket to incident', $message);
    }

    public function testFormatTicketChangesFormatsImpact(): void
    {
        $ticket = TicketFactory::createOne([
            'impact' => 'low',
        ]);
        $ticket->setImpact('medium');
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'impact');

        $this->assertStringContainsString('changed the impact', $message);
    }

    public function testFormatTicketChangesFormatsPriority(): void
    {
        $ticket = TicketFactory::createOne([
            'priority' => 'medium',
        ]);
        $ticket->setPriority('high');
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'priority');

        $this->assertStringContainsString('changed the priority', $message);
    }

    public function testFormatTicketChangesFormatsUrgency(): void
    {
        $ticket = TicketFactory::createOne([
            'urgency' => 'high',
        ]);
        $ticket->setUrgency('low');
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'urgency');

        $this->assertStringContainsString('changed the urgency', $message);
    }

    public function testFormatTicketChangesFormatsAssigneeWhenAssigned(): void
    {
        $assignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'assignee' => null,
        ]);
        $ticket->setAssignee($assignee->_real());
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'assignee');

        $this->assertStringContainsString('assigned the ticket', $message);
    }

    public function testFormatTicketChangesFormatsAssigneeWhenUnassigned(): void
    {
        $assignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'assignee' => $assignee,
        ]);
        $ticket->setAssignee(null);
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'assignee');

        $this->assertStringContainsString('removed the assignee', $message);
    }

    public function testFormatTicketChangesFormatsAssigneeWhenChanged(): void
    {
        $oldAssignee = UserFactory::createOne();
        $newAssignee = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'assignee' => $oldAssignee,
        ]);
        $ticket->setAssignee($newAssignee->_real());
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'assignee');

        $this->assertStringContainsString('changed the assignee', $message);
    }

    public function testFormatTicketChangesFormatsRequester(): void
    {
        $oldRequester = UserFactory::createOne();
        $newRequester = UserFactory::createOne();
        $ticket = TicketFactory::createOne([
            'requester' => $oldRequester,
        ]);
        $ticket->setRequester($newRequester->_real());
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'requester');

        $this->assertStringContainsString('changed the requester', $message);
    }

    public function testFormatTicketChangesFormatsSolutionWhenNew(): void
    {
        $solution = MessageFactory::createOne();
        $ticket = TicketFactory::createOne([
            'solution' => null,
        ]);
        $ticket->setSolution($solution->_real());
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'solution');

        $this->assertStringContainsString('added a solution', $message);
    }

    public function testFormatTicketChangesFormatsSolutionWhenRemoved(): void
    {
        $solution = MessageFactory::createOne();
        $ticket = TicketFactory::createOne([
            'solution' => $solution,
        ]);
        $ticket->setSolution(null);
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'solution');

        $this->assertStringContainsString('removed the solution', $message);
    }

    public function testFormatTicketChangesFormatsSolutionWhenChanged(): void
    {
        $oldSolution = MessageFactory::createOne();
        $newSolution = MessageFactory::createOne();
        $ticket = TicketFactory::createOne([
            'solution' => $oldSolution,
        ]);
        $ticket->setSolution($newSolution->_real());
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'solution');

        $this->assertStringContainsString('changed the solution', $message);
    }

    public function testFormatTicketChangesFormatsOrganizationWhenChanged(): void
    {
        $oldOrganization = OrganizationFactory::createOne([
            'name' => 'Foo',
        ]);
        $newOrganization = OrganizationFactory::createOne([
            'name' => 'Bar',
        ]);
        $ticket = TicketFactory::createOne([
            'organization' => $oldOrganization,
        ]);
        $ticket->setOrganization($newOrganization->_real());
        $event = $this->saveEvent($ticket);

        $message = $this->formatter->formatTicketChanges($event, 'organization');

        $message = strip_tags($message);
        $this->assertStringContainsString("transferred the ticket from Foo to Bar", $message);
    }

    public function testFormatTicketHandlesDeletedOrganizationsChanged(): void
    {
        $oldOrganization = OrganizationFactory::createOne([
            'name' => 'Foo',
        ]);
        $newOrganization = OrganizationFactory::createOne([
            'name' => 'Bar',
        ]);
        $ticket = TicketFactory::createOne([
            'organization' => $oldOrganization,
        ]);
        $ticket->setOrganization($newOrganization->_real());
        $event = $this->saveEvent($ticket);
        $oldOrganization->_delete();

        $message = $this->formatter->formatTicketChanges($event, 'organization');

        $message = strip_tags($message);
        $this->assertStringContainsString("transferred the ticket from deleted organization to Bar", $message);
    }
}
