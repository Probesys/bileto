<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\ActorsLister;
use App\TicketActivity\TicketEvent;
use App\Utils\ArrayHelper;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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

        $allUsers = $actorsLister->findByOrganization($organization);
        $agents = $actorsLister->findByOrganization($organization, roleType: 'agent');
        $requester = $ticket->getRequester();
        $assignee = $ticket->getAssignee();

        return $this->render('tickets/actors/edit.html.twig', [
            'ticket' => $ticket,
            'requesterUid' => $requester ? $requester->getUid() : '',
            'assigneeUid' => $assignee ? $assignee->getUid() : '',
            'allUsers' => $allUsers,
            'agents' => $agents,
        ]);
    }

    #[Route('/tickets/{uid}/actors/edit', name: 'update ticket actors', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TicketRepository $ticketRepository,
        UserRepository $userRepository,
        ActorsLister $actorsLister,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher,
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

        $allUsers = $actorsLister->findByOrganization($organization);
        $agents = $actorsLister->findByOrganization($organization, roleType: 'agent');

        if (!$this->isCsrfTokenValid('update ticket actors', $csrfToken)) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'allUsers' => $allUsers,
                'agents' => $agents,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $requester = ArrayHelper::find($allUsers, function ($user) use ($requesterUid): bool {
            return $user->getUid() === $requesterUid;
        });

        $assignee = null;
        if ($assigneeUid) {
            $assignee = ArrayHelper::find($agents, function ($agent) use ($assigneeUid): bool {
                return $agent->getUid() === $assigneeUid;
            });
        }

        $previousAssignee = $ticket->getAssignee();

        $ticket->setRequester($requester);
        $ticket->setAssignee($assignee);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'allUsers' => $allUsers,
                'agents' => $agents,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $ticketRepository->save($ticket, true);

        if ($previousAssignee != $assignee) {
            $ticketEvent = new TicketEvent($ticket);
            $eventDispatcher->dispatch($ticketEvent, TicketEvent::ASSIGNED);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
