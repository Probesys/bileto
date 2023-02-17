<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\AuthorizationRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\TicketSearcher;
use App\Service\TicketTimeline;
use App\Utils\Time;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TicketsController extends BaseController
{
    #[Route('/tickets', name: 'tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        AuthorizationRepository $authorizationRepository,
        OrganizationRepository $orgaRepository,
        UserRepository $userRepository,
        TicketSearcher $ticketSearcher,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $assigneeUid */
        $assigneeUid = $request->query->get('assignee', '');

        $orgaIds = $authorizationRepository->getAuthorizedOrganizationIds($user);
        if (in_array(null, $orgaIds)) {
            $organizations = $orgaRepository->findAll();
        } else {
            $organizations = $orgaRepository->findWithSubOrganizations($orgaIds);
        }

        $ticketSearcher->setOrganizations($organizations);
        $ticketSearcher->setCriteria('status', Ticket::OPEN_STATUSES);

        if ($assigneeUid === 'none') {
            $ticketSearcher->setCriteria('assignee', null);
            $currentPage = 'to assign';
        } elseif ($assigneeUid !== '') {
            $assignee = $userRepository->findOneBy(['uid' => $assigneeUid]);
            $ticketSearcher->setCriteria('assignee', $assignee);
            $currentPage = 'owned';
        } else {
            $currentPage = 'all';
        }

        return $this->render('tickets/index.html.twig', [
            'tickets' => $ticketSearcher->getTickets(),
            'countToAssign' => $ticketSearcher->countToAssign(),
            'countOwned' => $ticketSearcher->countAssignedTo($user),
            'currentPage' => $currentPage,
        ]);
    }

    #[Route('/tickets/{uid}', name: 'ticket', methods: ['GET', 'HEAD'])]
    public function show(
        Ticket $ticket,
        OrganizationRepository $organizationRepository,
        TicketTimeline $ticketTimeline,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $organization = $ticket->getOrganization();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        $statuses = Ticket::getStatusesWithLabels();
        if ($ticket->getStatus() !== 'new') {
            unset($statuses['new']);
        }

        $timeline = $ticketTimeline->build($ticket);

        return $this->render('tickets/show.html.twig', [
            'ticket' => $ticket,
            'timeline' => $timeline,
            'organization' => $organization,
            'today' => Time::relative('today'),
            'message' => '',
            'status' => 'pending',
            'statuses' => $statuses,
            'isSolution' => false,
            'isConfidential' => false,
        ]);
    }
}
