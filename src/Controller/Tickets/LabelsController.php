<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Form;
use App\Entity;
use App\Repository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LabelsController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/labels/edit', name: 'edit ticket labels', methods: ['GET', 'HEAD'])]
    public function edit(Entity\Ticket $ticket): Response
    {
        $this->denyAccessUnlessGranted('orga:update:tickets:labels', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('ticket_labels', Form\Ticket\LabelsForm::class, $ticket);

        return $this->render('tickets/labels/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/tickets/{uid:ticket}/labels/edit', name: 'update ticket labels', methods: ['POST'])]
    public function update(
        Entity\Ticket $ticket,
        Request $request,
        Repository\EntityEventRepository $entityEventRepository,
        Repository\TicketRepository $ticketRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:labels', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $initialLabels = $ticket->getLabels()->toArray();
        $initialLabelsIds = array_map(fn (Entity\Label $label): int => $label->getId(), $initialLabels);

        $form = $this->createNamedForm('ticket_labels', Form\Ticket\LabelsForm::class, $ticket);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('tickets/labels/edit.html.twig', [
                'ticket' => $ticket,
                'form' => $form,
            ]);
        }

        $ticket = $form->getData();
        $ticketRepository->save($ticket, true);

        $newLabels = $ticket->getLabels()->toArray();
        $newLabelsIds = array_map(fn (Entity\Label $label): int => $label->getId(), $newLabels);

        // Log changes to the labels field manually, as we cannot log
        // these automatically with the EntityActivitySubscriber (i.e. ManyToMany
        // relationships cannot be handled easily).
        if ($initialLabelsIds != $newLabelsIds) {
            $changes = [
                $initialLabelsIds,
                $newLabelsIds,
            ];

            $entityEvent = Entity\EntityEvent::initUpdate($ticket, [
                'labels' => $changes,
            ]);
            $entityEventRepository->save($entityEvent, true);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
