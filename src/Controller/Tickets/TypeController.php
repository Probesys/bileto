<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity;
use App\Repository;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TypeController extends BaseController
{
    public function __construct(
        private readonly Repository\TicketRepository $ticketRepository,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/tickets/{uid:ticket}/type/edit', name: 'update ticket type', methods: ['POST'])]
    public function update(Entity\Ticket $ticket, Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:update:tickets:type', $ticket);
        $this->denyAccessIfTicketIsClosed($ticket);

        $oldType = $ticket->getType();

        /** @var string $type */
        $type = $request->request->get('type', $oldType);

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update ticket type', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        $ticket->setType($type);

        $errors = $this->validator->validate($ticket);
        if (count($errors) > 0) {
            $error = implode(' ', ConstraintErrorsFormatter::format($errors));
            $this->addFlash('error', $error);
            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        $this->ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
