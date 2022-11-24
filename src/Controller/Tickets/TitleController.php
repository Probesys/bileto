<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TitleController extends BaseController
{
    #[Route('/tickets/{uid}/title/edit', name: 'edit ticket title', methods: ['GET', 'HEAD'])]
    public function edit(Ticket $ticket): Response
    {
        return $this->render('tickets/title/edit.html.twig', [
            'ticket' => $ticket,
            'title' => $ticket->getTitle(),
        ]);
    }

    #[Route('/tickets/{uid}/title/edit', name: 'update ticket title', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        ValidatorInterface $validator,
    ): Response {
        $oldTitle = $ticket->getTitle();

        /** @var string $title */
        $title = $request->request->get('title', $oldTitle);

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update ticket title', $csrfToken)) {
            return $this->renderBadRequest('tickets/title/edit.html.twig', [
                'ticket' => $ticket,
                'title' => $title,
                'error' => $this->csrfError(),
            ]);
        }

        $ticket->setTitle($title);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            $ticket->setTitle($oldTitle);
            return $this->renderBadRequest('tickets/title/edit.html.twig', [
                'ticket' => $ticket,
                'title' => $title,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
