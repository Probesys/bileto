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
use Symfony\Contracts\Translation\TranslatorInterface;

class PriorityController extends BaseController
{
    #[Route('/tickets/{uid}/priority/edit', name: 'edit ticket priority', methods: ['GET', 'HEAD'])]
    public function edit(Ticket $ticket): Response
    {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:priority', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        return $this->render('tickets/priority/edit.html.twig', [
            'ticket' => $ticket,
            'urgency' => $ticket->getUrgency(),
            'impact' => $ticket->getImpact(),
            'priority' => $ticket->getPriority(),
        ]);
    }

    #[Route('/tickets/{uid}/priority/edit', name: 'update ticket priority', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:priority', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        /** @var string $urgency */
        $urgency = $request->request->get('urgency', $ticket->getUrgency());

        /** @var string $impact */
        $impact = $request->request->get('impact', $ticket->getImpact());

        /** @var string $priority */
        $priority = $request->request->get('priority', $ticket->getPriority());

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update ticket priority', $csrfToken)) {
            return $this->renderBadRequest('tickets/priority/edit.html.twig', [
                'ticket' => $ticket,
                'urgency' => $urgency,
                'impact' => $impact,
                'priority' => $priority,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $ticket->setUrgency($urgency);
        $ticket->setImpact($impact);
        $ticket->setPriority($priority);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/priority/edit.html.twig', [
                'ticket' => $ticket,
                'urgency' => $urgency,
                'impact' => $impact,
                'priority' => $priority,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
