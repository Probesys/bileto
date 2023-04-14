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
use App\SearchEngine\TicketSearcher;
use App\Service\OrganizationSorter;
use App\Service\TicketTimeline;
use App\Utils\Time;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketsController extends BaseController
{
    #[Route('/tickets', name: 'tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        AuthorizationRepository $authorizationRepository,
        OrganizationRepository $orgaRepository,
        UserRepository $userRepository,
        TicketSearcher $ticketSearcher,
        TranslatorInterface $translator,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $view */
        $view = $request->query->get('view', 'all');

        /** @var string $queryString */
        $queryString = $request->query->get('q', '');

        $orgaIds = $authorizationRepository->getAuthorizedOrganizationIds($user);
        if (in_array(null, $orgaIds)) {
            $organizations = $orgaRepository->findAll();
        } else {
            $organizations = $orgaRepository->findWithSubOrganizations($orgaIds);
        }

        $ticketSearcher->setOrganizations($organizations);

        if ($queryString) {
            $queryString = trim($queryString);
        } elseif ($view === 'unassigned') {
            $queryString = TicketSearcher::QUERY_UNASSIGNED;
        } elseif ($view === 'owned') {
            $queryString = TicketSearcher::QUERY_OWNED;
        } else {
            $queryString = TicketSearcher::QUERY_DEFAULT;
        }

        $errors = [];

        try {
            $tickets = $ticketSearcher->getTickets($queryString);
        } catch (\Exception $e) {
            $tickets = [];
            $errors['search'] = $translator->trans('ticket.search.invalid', [], 'errors');
        }

        return $this->render('tickets/index.html.twig', [
            'tickets' => $tickets,
            'countToAssign' => $ticketSearcher->countTickets(TicketSearcher::QUERY_UNASSIGNED),
            'countOwned' => $ticketSearcher->countTickets(TicketSearcher::QUERY_OWNED),
            'view' => $view,
            'query' => $queryString,
            'errors' => $errors,
        ]);
    }

    #[Route('/tickets/new', name: 'new ticket', methods: ['GET', 'HEAD'])]
    public function new(
        Request $request,
        AuthorizationRepository $authorizationRepository,
        OrganizationRepository $organizationRepository,
        OrganizationSorter $organizationSorter,
        Security $security,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $selectedOrganizationUid = $request->query->get('organization');

        if ($selectedOrganizationUid) {
            $organization = $organizationRepository->findOneBy([
                'uid' => $selectedOrganizationUid,
            ]);

            if (!$organization) {
                throw $this->createNotFoundException('The organization does not exist.');
            }

            $organizations = [$organization];
        } else {
            $orgaIds = $authorizationRepository->getAuthorizedOrganizationIds($user);
            if (in_array(null, $orgaIds)) {
                $organizations = $organizationRepository->findAll();
            } else {
                $organizations = $organizationRepository->findWithSubOrganizations($orgaIds);
            }
        }

        // Keep only the organizations in which the user can create tickets
        $organizations = array_filter($organizations, function ($organization) use ($security) {
            return $security->isGranted('orga:create:tickets', $organization);
        });
        $organizations = array_values($organizations); // reset the keys of the array

        if (count($organizations) === 0) {
            throw $this->createAccessDeniedException();
        } elseif (count($organizations) === 1) {
            return $this->redirectToRoute('new organization ticket', [
                'uid' => $organizations[0]->getUid(),
            ]);
        }

        $organizations = $organizationSorter->asTree($organizations);

        return $this->render('tickets/new.html.twig', [
            'organizations' => $organizations,
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
