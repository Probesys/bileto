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
use Symfony\Contracts\Translation\TranslatorInterface;

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

        $users = $actorsLister->findAllForOrganization($organization);
        $requester = $ticket->getRequester();
        $assignee = $ticket->getAssignee();

        return $this->render('tickets/actors/edit.html.twig', [
            'ticket' => $ticket,
            'requesterUid' => $requester ? $requester->getUid() : '',
            'assigneeUid' => $assignee ? $assignee->getUid() : '',
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
        TranslatorInterface $translator,
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

        /** @var string $requesterUid */
        $requesterUid = $request->request->get('requesterUid', $initialRequester ? $initialRequester->getUid() : '');

        /** @var string $assigneeUid */
        $assigneeUid = $request->request->get('assigneeUid', $initialAssignee ? $initialAssignee->getUid() : '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $users = $actorsLister->findAllForOrganization($organization);

        if (!$this->isCsrfTokenValid('update ticket actors', $csrfToken)) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'users' => $users,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $requester = $userRepository->findOneBy(['uid' => $requesterUid]);
        if (!$requester) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'users' => $users,
                'errors' => [
                    'requester' => $translator->trans('ticket.requester.invalid', [], 'errors'),
                ],
            ]);
        }

        if ($assigneeUid) {
            $assignee = $userRepository->findOneBy(['uid' => $assigneeUid]);
            if (!$assignee) {
                return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                    'ticket' => $ticket,
                    'requesterUid' => $requesterUid,
                    'assigneeUid' => $assigneeUid,
                    'users' => $users,
                    'errors' => [
                        'assignee' => $translator->trans('ticket.assignee.invalid', [], 'errors'),
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
