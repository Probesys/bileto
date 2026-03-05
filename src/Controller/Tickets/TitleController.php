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
use Symfony\Component\Routing\Attribute\Route;

class TitleController extends BaseController
{
    public function __construct(
        private readonly Repository\TicketRepository $ticketRepository,
    ) {
    }

    #[Route('/tickets/{uid:ticket}/title/edit', name: 'edit ticket title')]
    public function edit(Entity\Ticket $ticket, Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:update:tickets:title', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $form = $this->createNamedForm('ticket_title', Form\Ticket\TitleForm::class, $ticket);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticket = $form->getData();
            $this->ticketRepository->save($ticket, true);

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('tickets/title/edit.html.twig', [
            'ticket' => $ticket,
            'form' => $form,
        ]);
    }
}
