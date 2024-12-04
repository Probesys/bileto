<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
use Symfony\Component\Routing\Annotation\Route;

class TicketsController extends BaseController
{
    #[Route('/organizations/{uid:organization}/tickets', name: 'organization tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Entity\Organization $organization,
        Request $request,
        SearchEngine\Ticket\Searcher $ticketSearcher,
        SearchEngine\Ticket\QuickSearchFilterBuilder $ticketQuickSearchFilterBuilder,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see', $organization);

        $page = $request->query->getInt('page', 1);
        $view = $request->query->getString('view', 'all');
        $searchMode = $request->query->getString('mode', 'quick');
        $sort = $request->query->getString('sort', 'updated-desc');

        if ($view === 'unassigned') {
            $defaultQuery = SearchEngine\Ticket\Searcher::queryUnassigned();
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
                'organization' => $organization,
                'from' => $this->generateUrl('organization tickets', [
                    'uid' => $organization->getUid(),
                ]),
            ]
        );

        $ticketSearcher->setOrganization($organization);

        if ($query) {
            $ticketsPagination = $ticketSearcher->getTickets($query, $sort, [
                'page' => $page,
                'maxResults' => 25,
            ]);
        } else {
            $ticketsPagination = Utils\Pagination::empty();
        }

        return $this->render('organizations/tickets/index.html.twig', [
            'organization' => $organization,
            'ticketsPagination' => $ticketsPagination,
            'countToAssign' => $ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryUnassigned()),
            'countOwned' => $ticketSearcher->countTickets(SearchEngine\Ticket\Searcher::queryOwned()),
            'view' => $view,
            'query' => $query?->getString(),
            'sort' => $sort,
            'searchMode' => $searchMode,
            'quickSearchForm' => $quickSearchForm,
            'advancedSearchForm' => $advancedSearchForm,
        ]);
    }

    #[Route('/organizations/{uid:organization}/tickets/new', name: 'new organization ticket')]
    public function new(
        Entity\Organization $organization,
        Request $request,
        Repository\TicketRepository $ticketRepository,
        Service\TicketAssigner $ticketAssigner,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets', $organization);

        /** @var Entity\User */
        $user = $this->getUser();

        $responsibleTeam = $ticketAssigner->getDefaultResponsibleTeam($organization);

        $ticket = new Entity\Ticket();
        $ticket->setOrganization($organization);
        $ticket->setRequester($user);
        $ticket->setTeam($responsibleTeam);

        $form = $this->createNamedForm('ticket', Form\TicketForm::class, $ticket);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $ticketRepository->save($ticket, true);

            $message = $ticket->getMessages()->first();

            if (!$message) {
                throw new \LogicException('Message must exist as Ticket content is set by TicketForm');
            }

            $ticketEvent = new TicketActivity\TicketEvent($ticket);
            $eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::CREATED);

            $messageEvent = new TicketActivity\MessageEvent($message);
            $eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED);

            if ($ticket->getAssignee() !== null) {
                $eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::ASSIGNED);
            }

            if ($ticket->getStatus() === 'resolved') {
                $eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::RESOLVED);
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
