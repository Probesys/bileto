<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatusController extends BaseController
{
    #[Route('/tickets/{uid:ticket}/status/edit', name: 'edit ticket status', methods: ['GET', 'HEAD'])]
    public function edit(Ticket $ticket): Response
    {
        $this->denyAccessUnlessGranted('orga:update:tickets:status', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $statuses = Ticket::getStatusesWithLabels();

        return $this->render('tickets/status/edit.html.twig', [
            'ticket' => $ticket,
            'status' => $ticket->getStatus(),
            'statuses' => $statuses,
        ]);
    }

    #[Route('/tickets/{uid:ticket}/status/edit', name: 'update ticket status', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:status', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        /** @var string $status */
        $status = $request->request->get('status', $ticket->getStatus());

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $statuses = Ticket::getStatusesWithLabels();

        if (!$this->isCsrfTokenValid('update ticket status', $csrfToken)) {
            return $this->renderBadRequest('tickets/status/edit.html.twig', [
                'ticket' => $ticket,
                'status' => $status,
                'statuses' => $statuses,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $ticket->setStatus($status);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/status/edit.html.twig', [
                'ticket' => $ticket,
                'status' => $status,
                'statuses' => $statuses,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }

    #[Route('/tickets/{uid:ticket}/status/reopening', name: 'reopen ticket', methods: ['POST'])]
    public function reopen(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:update:tickets:status', $ticket);

        if (!$ticket->isClosed()) {
            throw $this->createAccessDeniedException('Access denied because ticket is not closed.');
        }

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('reopen ticket', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        if ($ticket->getAssignee() === null) {
            $ticket->setStatus('new');
        } else {
            $ticket->setStatus('in_progress');
        }

        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
