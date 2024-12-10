<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\SearchEngine;
use App\Security;
use App\Service;
use App\Utils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TicketsController extends BaseController
{
    #[Route('/tickets', name: 'tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        Repository\OrganizationRepository $organizationRepository,
        SearchEngine\Ticket\Searcher $ticketSearcher,
        SearchEngine\Ticket\QuickSearchFilterBuilder $ticketQuickSearchFilterBuilder,
        Service\UserService $userService,
    ): Response {
        $page = $request->query->getInt('page', 1);
        $view = $request->query->getString('view', 'all');
        $searchMode = $request->query->getString('mode', 'quick');
        $sort = $request->query->getString('sort', 'updated-desc');

        if ($view === 'unassigned') {
            $defaultQuery = SearchEngine\Ticket\Searcher::queryUnassigned();
        } elseif ($view === 'assigned-me') {
            $defaultQuery = SearchEngine\Ticket\Searcher::queryAssignedMe();
        } elseif ($view === 'owned') {
            $defaultQuery = SearchEngine\Ticket\Searcher::queryOwned();
        } else {
            $defaultQuery = SearchEngine\Ticket\Searcher::queryDefault();
        }

        $advancedSearchForm = $this->createNamedForm('', Form\AdvancedSearchForm::class, [
            'q' => $defaultQuery,
        ]);
        $advancedSearchForm->handleRequest($request);

        $query = $advancedSearchForm->get('q')->getData();

        $ticketQuickSearchFilter = $ticketQuickSearchFilterBuilder->getFilter($query);
        $quickSearchForm = $this->createNamedForm(
            'search',
            Form\Ticket\QuickSearchForm::class,
            $ticketQuickSearchFilter,
            options: [
                'from' => $this->generateUrl('tickets'),
            ],
        );

        /** @var Entity\User */
        $user = $this->getUser();
        $organizations = $organizationRepository->findAuthorizedOrganizations($user);

        $ticketSearcher->setOrganizations($organizations);

        if ($query) {
            $ticketsPagination = $ticketSearcher->getTickets($query, $sort, [
                'page' => $page,
                'maxResults' => 25,
            ]);
        } else {
            $ticketsPagination = Utils\Pagination::empty();
        }

        $defaultOrganization = $userService->getDefaultOrganization($user);

        return $this->render('tickets/index.html.twig', [
            'ticketsPagination' => $ticketsPagination,
            'countToAssign' => $ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryUnassigned()),
            'countAssignedMe' => $ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryAssignedMe()),
            'countOwned' => $ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryOwned()),
            'view' => $view,
            'query' => $query?->getString(),
            'sort' => $sort,
            'searchMode' => $searchMode,
            'quickSearchForm' => $quickSearchForm,
            'advancedSearchForm' => $advancedSearchForm,
            'defaultOrganization' => $defaultOrganization,
        ]);
    }

    #[Route('/tickets/{uid:ticket}', name: 'ticket', methods: ['GET', 'HEAD'])]
    public function show(
        Entity\Ticket $ticket,
        Service\TicketTimeline $ticketTimeline,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see', $ticket);

        $timeline = $ticketTimeline->build($ticket);

        $message = new Entity\Message();
        $message->setTicket($ticket);

        $form = $this->createNamedForm('answer', Form\AnswerForm::class, $message);

        return $this->render('tickets/show.html.twig', [
            'ticket' => $ticket,
            'timeline' => $timeline,
            'organization' => $ticket->getOrganization(),
            'today' => Utils\Time::relative('today'),
            'form' => $form,
        ]);
    }
}
