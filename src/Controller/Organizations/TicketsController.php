<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\SearchEngine;
use App\Service;
use App\TicketActivity;
use App\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TicketsController extends BaseController
{
    public function __construct(
        private readonly SearchEngine\Ticket\Searcher $ticketSearcher,
        private readonly SearchEngine\Ticket\QuickSearchFilterBuilder $ticketQuickSearchFilterBuilder,
        private readonly Repository\TicketRepository $ticketRepository,
        private readonly Service\TicketAssigner $ticketAssigner,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/organizations/{uid:organization}/tickets', name: 'organization tickets', methods: ['GET', 'HEAD'])]
    public function index(Entity\Organization $organization, Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:see', $organization);

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

        $ticketQuickSearchFilter = $this->ticketQuickSearchFilterBuilder->getFilter($query);
        $quickSearchForm = $this->createNamedForm(
            'search',
            Form\Ticket\QuickSearchForm::class,
            $ticketQuickSearchFilter,
            options: [
                'organization' => $organization,
                'from' => $this->generateUrl('organization tickets', [
                    'uid' => $organization->getUid(),
                ]),
            ]
        );

        $this->ticketSearcher->setOrganization($organization);

        if ($query) {
            $ticketsPagination = $this->ticketSearcher->getTickets($query, $sort, [
                'page' => $page,
                'maxResults' => 25,
            ]);
        } else {
            $ticketsPagination = Utils\Pagination::empty();
        }

        return $this->render('organizations/tickets/index.html.twig', [
            'organization' => $organization,
            'ticketsPagination' => $ticketsPagination,
            'countToAssign' => $this->ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryUnassigned()),
            'countAssignedMe' => $this->ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryAssignedMe()),
            'countOwned' => $this->ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryOwned()),
            'view' => $view,
            'query' => $query?->getString(),
            'sort' => $sort,
            'searchMode' => $searchMode,
            'quickSearchForm' => $quickSearchForm,
            'advancedSearchForm' => $advancedSearchForm,
        ]);
    }

    #[Route('/organizations/{uid:organization}/tickets/new', name: 'new organization ticket')]
    public function new(Entity\Organization $organization, Request $request): Response
    {
        $this->denyAccessUnlessGranted('orga:create:tickets', $organization);

        /** @var Entity\User */
        $user = $this->getUser();

        $responsibleTeam = $this->ticketAssigner->getDefaultResponsibleTeam($organization);

        $ticket = new Entity\Ticket();
        $ticket->setOrganization($organization);
        $ticket->setRequester($user);
        $ticket->setTeam($responsibleTeam);

        foreach ($organization->getObservers() as $observer) {
            $ticket->addObserver($observer);
        }

        $form = $this->createNamedForm('ticket', Form\TicketForm::class, $ticket);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->ticketRepository->save($ticket, true);

            $message = $ticket->getMessages()->first();

            if (!$message) {
                throw new \LogicException('Message must exist as Ticket content is set by TicketForm');
            }

            $ticketEvent = new TicketActivity\TicketEvent($ticket);
            $this->eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::CREATED);

            $messageEvent = new TicketActivity\MessageEvent($message);
            $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED);

            if ($ticket->getAssignee() !== null) {
                $this->eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::ASSIGNED);
            }

            return $this->redirectToRoute('ticket', [
                'uid' => $ticket->getUid(),
            ]);
        }

        return $this->render('organizations/tickets/new.html.twig', [
            'organization' => $organization,
            'form' => $form,
        ]);
    }
}
