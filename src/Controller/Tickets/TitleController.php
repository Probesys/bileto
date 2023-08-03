<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
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

class TitleController extends BaseController
{
    #[Route('/tickets/{uid}/title/edit', name: 'edit ticket title', methods: ['GET', 'HEAD'])]
    public function edit(Ticket $ticket): Response
    {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:title', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

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
        TranslatorInterface $translator,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:title', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $oldTitle = $ticket->getTitle();

        /** @var string $title */
        $title = $request->request->get('title', $oldTitle);

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update ticket title', $csrfToken)) {
            return $this->renderBadRequest('tickets/title/edit.html.twig', [
                'ticket' => $ticket,
                'title' => $title,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $ticket->setTitle($title);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            $ticket->setTitle($oldTitle);
            return $this->renderBadRequest('tickets/title/edit.html.twig', [
                'ticket' => $ticket,
                'title' => $title,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
