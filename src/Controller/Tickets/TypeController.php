<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TypeController extends BaseController
{
    #[Route('/tickets/{uid}/type/edit', name: 'update ticket type', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        ValidatorInterface $validator
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:type', $organization);

        $oldType = $ticket->getType();

        /** @var string $type */
        $type = $request->request->get('type', $oldType);

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update ticket type', $csrfToken)) {
            $this->addFlash('error', $this->csrfError());
            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        $ticket->setType($type);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            $error = implode(' ', $this->formatErrors($errors));
            $this->addFlash('error', $error);
            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
