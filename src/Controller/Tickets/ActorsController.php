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
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

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
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

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

        $newAssignee = $ticket->getAssignee();

        if ($initialAssignee != $newAssignee) {
            $ticketEvent = new TicketEvent($ticket);
            $eventDispatcher->dispatch($ticketEvent, TicketEvent::ASSIGNED);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
