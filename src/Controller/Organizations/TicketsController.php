<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\SearchEngine\Query;
use App\SearchEngine\TicketFilter;
use App\SearchEngine\TicketSearcher;
use App\Service\ActorsLister;
use App\Service\Sorter\LabelSorter;
use App\Service\TicketAssigner;
use App\TicketActivity;
use App\Utils\Pagination;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketsController extends BaseController
{
    #[Route('/organizations/{uid:organization}/tickets', name: 'organization tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Entity\Organization $organization,
        Request $request,
        Repository\LabelRepository $labelRepository,
        Repository\OrganizationRepository $organizationRepository,
        Repository\UserRepository $userRepository,
        TicketSearcher $ticketSearcher,
        ActorsLister $actorsLister,
        LabelSorter $labelSorter,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see', $organization);

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

        $ticketSearcher->setOrganization($organization);

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

        return $this->render('organizations/tickets/index.html.twig', [
            'organization' => $organization,
            'ticketsPagination' => $ticketsPagination,
            'countToAssign' => $ticketSearcher->countTickets(TicketSearcher::queryUnassigned()),
            'countOwned' => $ticketSearcher->countTickets(TicketSearcher::queryOwned()),
            'view' => $view,
            'query' => $queryString,
            'sort' => $sort,
            'ticketFilter' => $ticketFilter,
            'searchMode' => $searchMode,
            'openStatuses' => Entity\Ticket::getStatusesWithLabels('open'),
            'finishedStatuses' => Entity\Ticket::getStatusesWithLabels('finished'),
            'allUsers' => $actorsLister->findByOrganization($organization),
            'agents' => $actorsLister->findByOrganization($organization, roleType: 'agent'),
            'labels' => $labels,
            'errors' => $errors,
        ]);
    }

    #[Route('/organizations/{uid:organization}/tickets/new', name: 'new organization ticket')]
    public function new(
        Entity\Organization $organization,
        Request $request,
        Repository\TicketRepository $ticketRepository,
        TicketAssigner $ticketAssigner,
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
