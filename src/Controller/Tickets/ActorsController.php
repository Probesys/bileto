<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\ActorsLister;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;

class ActorsController extends BaseController
{
    #[Route('/tickets/{uid}/actors/edit', name: 'edit ticket actors', methods: ['GET', 'HEAD'])]
    public function edit(
        Ticket $ticket,
        ActorsLister $actorsLister,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $users = $actorsLister->listUsers();
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
        UserRepository $userRepository,
        ActorsLister $actorsLister,
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $initialRequester = $ticket->getRequester();
        $initialAssignee = $ticket->getAssignee();

        /** @var int $requesterId */
        $requesterId = $request->request->getInt('requesterId', $initialRequester ? $initialRequester->getId() : 0);

        /** @var int $assigneeId */
        $assigneeId = $request->request->getInt('assigneeId', $initialAssignee ? $initialAssignee->getId() : 0);

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $users = $actorsLister->listUsers();

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
                    'requester' => new TranslatableMessage('The requester must exist.', [], 'validators'),
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
                        'assignee' => new TranslatableMessage('The assignee must exist.', [], 'validators'),
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
