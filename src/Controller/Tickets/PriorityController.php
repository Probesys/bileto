<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PriorityController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/priority/edit', name: 'edit ticket priority')]
    public function edit(
        Entity\Ticket $ticket,
        Request $request,
        Repository\TicketRepository $ticketRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:priority', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('ticket_priority', Form\Ticket\PriorityForm::class, $ticket);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticket = $form->getData();
            $ticketRepository->save($ticket, true);

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('tickets/priority/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }
}
