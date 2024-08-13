<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Service\ActorsLister;
use App\Service\Sorter\TeamSorter;
use App\TicketActivity\TicketEvent;
use App\Utils\ArrayHelper;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActorsController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/actors/edit', name: 'edit ticket actors', methods: ['GET', 'HEAD'])]
    public function edit(Entity\Ticket $ticket): Response
    {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $organization);

        /** @var Entity\User */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $form = $this->createNamedForm('ticket_actors', Form\Ticket\ActorsForm::class, $ticket);

        return $this->render('tickets/actors/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/tickets/{uid:ticket}/actors/edit', name: 'update ticket actors', methods: ['POST'])]
    public function update(
        Entity\Ticket $ticket,
        Request $request,
        Repository\TicketRepository $ticketRepository,
        Repository\EntityEventRepository $entityEventRepository,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $organization);

        /** @var Entity\User */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $initialObservers = $ticket->getObservers()->toArray();
        $initialObserversIds = array_map(fn (Entity\User $observer): int => $observer->getId(), $initialObservers);
        $initialAssignee = $ticket->getAssignee();

        $form = $this->createNamedForm('ticket_actors', Form\Ticket\ActorsForm::class, $ticket);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'form' => $form,
            ]);
        }

        $ticket = $form->getData();
        $ticketRepository->save($ticket, true);

        $newObservers = $ticket->getObservers()->toArray();
        $newObserversIds = array_map(fn (Entity\User $observer): int => $observer->getId(), $newObservers);
        $newAssignee = $ticket->getAssignee();

        if ($initialAssignee != $newAssignee) {
            $ticketEvent = new TicketEvent($ticket);
            $eventDispatcher->dispatch($ticketEvent, TicketEvent::ASSIGNED);
        }

        // Log changes to the observers field manually, as we cannot log
        // these automatically with the EntityActivitySubscriber (i.e. ManyToMany
        // relationships cannot be handled easily).
        if ($initialObserversIds != $newObserversIds) {
            $changes = [
                $initialObserversIds,
                $newObserversIds,
            ];

            $entityEvent = Entity\EntityEvent::initUpdate($ticket, [
                'observers' => $changes,
            ]);
            $entityEventRepository->save($entityEvent, true);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
