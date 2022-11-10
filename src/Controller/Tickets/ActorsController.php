<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class ActorsController extends BaseController
{
    #[Route('/tickets/{uid}/actors/edit', name: 'edit ticket actors', methods: ['GET', 'HEAD'])]
    public function edit(
        Ticket $ticket,
        UserRepository $userRepository
    ): Response {
        $users = $userRepository->findBy([], ['email' => 'ASC']);
        $requester = $ticket->getRequester();
        $assignee = $ticket->getAssignee();
        return $this->render('tickets/actors/edit.html.twig', [
            'ticket' => $ticket,
            'requesterId' => $requester ? $requester->getId() : null,
            'assigneeId' => $assignee ? $assignee->getId() : null,
            'users' => $users,
        ]);
    }

    #[Route('/tickets/{uid}/actors/edit', name: 'update ticket actors', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        UserRepository $userRepository
    ): Response {
        $initialRequester = $ticket->getRequester();
        $initialAssignee = $ticket->getAssignee();

        /** @var string $requesterId */
        $requesterId = $request->request->get('requesterId', $initialRequester ? $initialRequester->getId() : '');

        /** @var string $assigneeId */
        $assigneeId = $request->request->get('assigneeId', $initialAssignee ? $initialAssignee->getId() : '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $users = $userRepository->findBy([], ['email' => 'ASC']);

        if (!$this->isCsrfTokenValid('update ticket actors', $csrfToken)) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterId' => $requesterId,
                'assigneeId' => $assigneeId,
                'users' => $users,
                'error' => $this->csrfError(),
            ]);
        }

        $requester = $userRepository->find($requesterId);
        if (!$requester) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterId' => $requesterId,
                'assigneeId' => $assigneeId,
                'users' => $users,
                'errors' => [
                    'requester' => new TranslatableMessage('The requester must exist.'),
                ],
            ]);
        }

        if ($assigneeId) {
            $assignee = $userRepository->find($assigneeId);
            if (!$assignee) {
                return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                    'ticket' => $ticket,
                    'requesterId' => $requesterId,
                    'assigneeId' => $assigneeId,
                    'users' => $users,
                    'errors' => [
                        'assignee' => new TranslatableMessage('The assignee must exist.'),
                    ],
                ]);
            }
        } else {
            $assignee = null;
        }

        $ticket->setRequester($requester);
        $ticket->setAssignee($assignee);
        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
