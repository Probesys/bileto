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
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketsController extends BaseController
{
    #[Route('/tickets', name: 'tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Request $request,
        Repository\AuthorizationRepository $authorizationRepository,
        Repository\LabelRepository $labelRepository,
        Repository\OrganizationRepository $orgaRepository,
        Repository\UserRepository $userRepository,
        SearchEngine\TicketSearcher $ticketSearcher,
        Service\ActorsLister $actorsLister,
        Service\Sorter\LabelSorter $labelSorter,
        TranslatorInterface $translator,
    ): Response {
        /** @var \App\Entity\User */
        $user = $this->getUser();

        $page = $request->query->getInt('page', 1);
        $view = $request->query->getString('view', 'all');
        $searchMode = $request->query->getString('mode', 'quick');
        $sort = $request->query->getString('sort', 'updated-desc');
        /** @var ?string */
        $queryString = $request->query->get('q');

        $organizations = $orgaRepository->findAuthorizedOrganizations($user);

        $ticketSearcher->setOrganizations($organizations);

        if ($queryString !== null) {
            $queryString = trim($queryString);
        } elseif ($view === 'unassigned') {
            $queryString = SearchEngine\TicketSearcher::QUERY_UNASSIGNED;
        } elseif ($view === 'owned') {
            $queryString = SearchEngine\TicketSearcher::QUERY_OWNED;
        } else {
            $queryString = SearchEngine\TicketSearcher::QUERY_DEFAULT;
        }

        $ticketFilter = null;
        $errors = [];

        try {
            $query = SearchEngine\Query::fromString($queryString);
            $ticketsPagination = $ticketSearcher->getTickets($query, $sort, [
                'page' => $page,
                'maxResults' => 25,
            ]);
            if ($query) {
                $ticketFilter = SearchEngine\TicketFilter::fromQuery($query);
            }
        } catch (\Exception $e) {
            $ticketsPagination = Utils\Pagination::empty();
            $errors['search'] = $translator->trans('ticket.search.invalid', [], 'errors');
        }

        if (!$ticketFilter) {
            $searchMode = 'advanced';
            $ticketFilter = new SearchEngine\TicketFilter();
        }

        $labels = $labelRepository->findAll();
        $labelSorter->sort($labels);

        return $this->render('tickets/index.html.twig', [
            'ticketsPagination' => $ticketsPagination,
            'countToAssign' => $ticketSearcher->countTickets(SearchEngine\TicketSearcher::queryUnassigned()),
            'countOwned' => $ticketSearcher->countTickets(SearchEngine\TicketSearcher::queryOwned()),
            'view' => $view,
            'query' => $queryString,
            'sort' => $sort,
            'ticketFilter' => $ticketFilter,
            'searchMode' => $searchMode,
            'openStatuses' => Entity\Ticket::getStatusesWithLabels('open'),
            'finishedStatuses' => Entity\Ticket::getStatusesWithLabels('finished'),
            'allUsers' => $actorsLister->findAll(),
            'agents' => $actorsLister->findAll(roleType: 'agent'),
            'labels' => $labels,
            'errors' => $errors,
        ]);
    }

    #[Route('/tickets/new', name: 'new ticket', methods: ['GET', 'HEAD'])]
    public function new(
        Request $request,
        Repository\AuthorizationRepository $authorizationRepository,
        Repository\OrganizationRepository $organizationRepository,
        Service\UserService $userService,
        Service\Sorter\OrganizationSorter $organizationSorter,
        Security\Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets', 'any');

        /** @var \App\Entity\User */
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
