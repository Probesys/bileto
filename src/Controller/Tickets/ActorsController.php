<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\TeamRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\ActorsLister;
use App\Service\Sorter\TeamSorter;
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
        TeamSorter $teamSorter,
        TeamRepository $teamRepository,
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
        $teams = $teamRepository->findByOrganization($organization);
        $teamSorter->sort($teams);

        $requester = $ticket->getRequester();
        $team = $ticket->getTeam();
        $assignee = $ticket->getAssignee();

        return $this->render('tickets/actors/edit.html.twig', [
            'ticket' => $ticket,
            'requesterUid' => $requester ? $requester->getUid() : '',
            'teamUid' => $team ? $team->getUid() : '',
            'assigneeUid' => $assignee ? $assignee->getUid() : '',
            'allUsers' => $allUsers,
            'teams' => $teams,
            'agents' => $agents,
        ]);
    }

    #[Route('/tickets/{uid}/actors/edit', name: 'update ticket actors', methods: ['POST'])]
    public function update(
        Ticket $ticket,
        Request $request,
        TeamRepository $teamRepository,
        TicketRepository $ticketRepository,
        UserRepository $userRepository,
        ActorsLister $actorsLister,
        TeamSorter $teamSorter,
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
        $initialTeam = $ticket->getTeam();
        $initialAssignee = $ticket->getAssignee();

        /** @var string $requesterUid */
        $requesterUid = $request->request->get('requesterUid', $initialRequester ? $initialRequester->getUid() : '');

        /** @var string $teamUid */
        $teamUid = $request->request->get('teamUid', $initialTeam ? $initialTeam->getUid() : '');

        /** @var string $assigneeUid */
        $assigneeUid = $request->request->get('assigneeUid', $initialAssignee ? $initialAssignee->getUid() : '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $allUsers = $actorsLister->findByOrganization($organization);
        $agents = $actorsLister->findByOrganization($organization, roleType: 'agent');
        $teams = $teamRepository->findByOrganization($organization);
        $teamSorter->sort($teams);

        if (!$this->isCsrfTokenValid('update ticket actors', $csrfToken)) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterUid' => $requesterUid,
                'teamUid' => $teamUid,
                'assigneeUid' => $assigneeUid,
                'allUsers' => $allUsers,
                'teams' => $teams,
                'agents' => $agents,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $requester = ArrayHelper::find($allUsers, function ($user) use ($requesterUid): bool {
            return $user->getUid() === $requesterUid;
        });

        $team = null;
        if ($teamUid) {
            $team = ArrayHelper::find($teams, function ($team) use ($teamUid): bool {
                return $team->getUid() === $teamUid;
            });
        }

        $assignee = null;
        if ($assigneeUid) {
            $availableAgents = $team ? $team->getAgents()->toArray() : $agents;
            $assignee = ArrayHelper::find($availableAgents, function ($agent) use ($assigneeUid): bool {
                return $agent->getUid() === $assigneeUid;
            });
        }

        $previousAssignee = $ticket->getAssignee();

        $ticket->setRequester($requester);
        $ticket->setTeam($team);
        $ticket->setAssignee($assignee);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/actors/edit.html.twig', [
                'ticket' => $ticket,
                'requesterUid' => $requesterUid,
                'teamUid' => $teamUid,
                'assigneeUid' => $assigneeUid,
                'allUsers' => $allUsers,
                'teams' => $teams,
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
