<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\Repository\AuthorizationRepository;
use App\Repository\LabelRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\SearchEngine\TicketFilter;
use App\SearchEngine\TicketSearcher;
use App\SearchEngine\Query;
use App\Security\Authorizer;
use App\Service\ActorsLister;
use App\Service\Sorter\LabelSorter;
use App\Service\Sorter\OrganizationSorter;
use App\Service\TicketTimeline;
use App\Service\UserService;
use App\Utils\Pagination;
use App\Utils\Time;
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
        LabelRepository $labelRepository,
        OrganizationRepository $orgaRepository,
        UserRepository $userRepository,
        TicketSearcher $ticketSearcher,
        ActorsLister $actorsLister,
        LabelSorter $labelSorter,
        TranslatorInterface $translator,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $page = $request->query->getInt('page', 1);

        /** @var string $view */
        $view = $request->query->get('view', 'all');

        /** @var ?string $queryString */
        $queryString = $request->query->get('q');

        /** @var string $searchMode */
        $searchMode = $request->query->get('mode', 'quick');

        /** @var string $sort */
        $sort = $request->query->get('sort', 'updated-desc');

        $organizations = $orgaRepository->findAuthorizedOrganizations($user);

        $ticketSearcher->setOrganizations($organizations);

        if ($queryString !== null) {
            $queryString = trim($queryString);
        } elseif ($view === 'unassigned') {
            $queryString = TicketSearcher::QUERY_UNASSIGNED;
        } elseif ($view === 'owned') {
            $queryString = TicketSearcher::QUERY_OWNED;
        } else {
            $queryString = TicketSearcher::QUERY_DEFAULT;
        }

        $ticketFilter = null;
        $errors = [];

        try {
            $query = Query::fromString($queryString);
            $ticketsPagination = $ticketSearcher->getTickets($query, $sort, [
                'page' => $page,
                'maxResults' => 25,
            ]);
            if ($query) {
                $ticketFilter = TicketFilter::fromQuery($query);
            }
        } catch (\Exception $e) {
            $ticketsPagination = Pagination::empty();
            $errors['search'] = $translator->trans('ticket.search.invalid', [], 'errors');
        }

        if (!$ticketFilter) {
            $searchMode = 'advanced';
            $ticketFilter = new TicketFilter();
        }

        $labels = $labelRepository->findAll();
        $labelSorter->sort($labels);

        return $this->render('tickets/index.html.twig', [
            'ticketsPagination' => $ticketsPagination,
            'countToAssign' => $ticketSearcher->countTickets(TicketSearcher::queryUnassigned()),
            'countOwned' => $ticketSearcher->countTickets(TicketSearcher::queryOwned()),
            'view' => $view,
            'query' => $queryString,
            'sort' => $sort,
            'ticketFilter' => $ticketFilter,
            'searchMode' => $searchMode,
            'openStatuses' => Ticket::getStatusesWithLabels('open'),
            'finishedStatuses' => Ticket::getStatusesWithLabels('finished'),
            'allUsers' => $actorsLister->findAll(),
            'agents' => $actorsLister->findAll(roleType: 'agent'),
            'labels' => $labels,
            'errors' => $errors,
        ]);
    }

    #[Route('/tickets/new', name: 'new ticket', methods: ['GET', 'HEAD'])]
    public function new(
        Request $request,
        AuthorizationRepository $authorizationRepository,
        OrganizationRepository $organizationRepository,
        OrganizationSorter $organizationSorter,
        UserService $userService,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets', 'any');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $selectedOrganizationUid = $request->query->get('organization');

        // If the user has a default organization and can create tickets in it,
        // just redirect to the "new ticket" form of this organization.
        $defaultOrganization = $userService->getDefaultOrganization($user);

        if ($defaultOrganization && $authorizer->isGranted('orga:create:tickets', $defaultOrganization)) {
            return $this->redirectToRoute('new organization ticket', [
                'uid' => $defaultOrganization->getUid(),
            ]);
        }

        // Otherwise, load the list of organizations they have access to (or
        // only the selected organization, i.e. once the user has submitted the
        // form).
        if ($selectedOrganizationUid) {
            $organization = $organizationRepository->findOneBy([
                'uid' => $selectedOrganizationUid,
            ]);

            if (!$organization) {
                throw $this->createNotFoundException('The organization does not exist.');
            }

            $organizations = [$organization];
        } else {
            $organizations = $organizationRepository->findAuthorizedOrganizations($user);
        }

        // Keep only the organizations in which the user can create tickets
        $organizations = array_filter($organizations, function ($organization) use ($authorizer): bool {
            return $authorizer->isGranted('orga:create:tickets', $organization);
        });
        $organizations = array_values($organizations); // reset the keys of the array

        if (count($organizations) === 1) {
            // The user has the permission to create tickets in only one
            // organization, so let's redirect to it.
            return $this->redirectToRoute('new organization ticket', [
                'uid' => $organizations[0]->getUid(),
            ]);
        }

        // Finally, let the user choose in which organization they want to
        // create a ticket.
        $organizationSorter->sort($organizations);

        return $this->render('tickets/new.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    #[Route('/tickets/{uid:ticket}', name: 'ticket', methods: ['GET', 'HEAD'])]
    public function show(Ticket $ticket, TicketTimeline $ticketTimeline): Response
    {
        $this->denyAccessUnlessGranted('orga:see', $ticket);

        $timeline = $ticketTimeline->build($ticket);

        return $this->render('tickets/show.html.twig', [
            'ticket' => $ticket,
            'timeline' => $timeline,
            'organization' => $ticket->getOrganization(),
            'today' => Time::relative('today'),
            'message' => '',
            'answerType' => 'normal',
            'minutesSpent' => 0,
        ]);
    }
}
