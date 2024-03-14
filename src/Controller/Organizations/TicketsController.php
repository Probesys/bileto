<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Ticket;
use App\Repository\ContractRepository;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\SearchEngine\Query;
use App\SearchEngine\TicketFilter;
use App\SearchEngine\TicketSearcher;
use App\Security\Authorizer;
use App\Service\ActorsLister;
use App\TicketActivity\MessageEvent;
use App\TicketActivity\TicketEvent;
use App\Utils\ArrayHelper;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Pagination;
use App\Utils\Time;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketsController extends BaseController
{
    #[Route('/organizations/{uid}/tickets', name: 'organization tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Organization $organization,
        Request $request,
        OrganizationRepository $organizationRepository,
        TicketSearcher $ticketSearcher,
        UserRepository $userRepository,
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

        return $this->render('organizations/tickets/index.html.twig', [
            'organization' => $organization,
            'ticketsPagination' => $ticketsPagination,
            'countToAssign' => $ticketSearcher->countTickets(TicketSearcher::queryUnassigned()),
            'countOwned' => $ticketSearcher->countTickets(TicketSearcher::queryOwned()),
            'view' => $view,
            'query' => $queryString,
            'ticketFilter' => $ticketFilter,
            'searchMode' => $searchMode,
            'errors' => $errors,
        ]);
    }

    #[Route('/organizations/{uid}/tickets/new', name: 'new organization ticket', methods: ['GET', 'HEAD'])]
    public function new(
        Organization $organization,
        ActorsLister $actorsLister,
        OrganizationRepository $organizationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets', $organization);

        $allUsers = $actorsLister->findByOrganization($organization);
        $agents = $actorsLister->findByOrganization($organization, roleType: 'agent');

        return $this->render('organizations/tickets/new.html.twig', [
            'organization' => $organization,
            'title' => '',
            'message' => '',
            'type' => Ticket::DEFAULT_TYPE,
            'requesterUid' => '',
            'assigneeUid' => '',
            'isResolved' => false,
            'urgency' => Ticket::DEFAULT_WEIGHT,
            'impact' => Ticket::DEFAULT_WEIGHT,
            'priority' => Ticket::DEFAULT_WEIGHT,
            'allUsers' => $allUsers,
            'agents' => $agents,
        ]);
    }

    #[Route('/organizations/{uid}/tickets/new', name: 'create organization ticket', methods: ['POST'])]
    public function create(
        Organization $organization,
        Request $request,
        ContractRepository $contractRepository,
        MessageRepository $messageRepository,
        OrganizationRepository $organizationRepository,
        TicketRepository $ticketRepository,
        UserRepository $userRepository,
        ActorsLister $actorsLister,
        Authorizer $authorizer,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $title */
        $title = $request->request->get('title', '');

        /** @var string $messageContent */
        $messageContent = $request->request->get('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        if ($authorizer->isGranted('orga:update:tickets:type', $organization)) {
            /** @var string $type */
            $type = $request->request->get('type', Ticket::DEFAULT_TYPE);
        } else {
            $type = Ticket::DEFAULT_TYPE;
        }

        if ($authorizer->isGranted('orga:update:tickets:actors', $organization)) {
            /** @var string $requesterUid */
            $requesterUid = $request->request->get('requesterUid', '');

            /** @var string $assigneeUid */
            $assigneeUid = $request->request->get('assigneeUid', '');
        } else {
            $requesterUid = $user->getUid();
            $assigneeUid = '';
        }

        if ($authorizer->isGranted('orga:update:tickets:priority', $organization)) {
            /** @var string $urgency */
            $urgency = $request->request->get('urgency', Ticket::DEFAULT_WEIGHT);

            /** @var string $impact */
            $impact = $request->request->get('impact', Ticket::DEFAULT_WEIGHT);

            /** @var string $priority */
            $priority = $request->request->get('priority', Ticket::DEFAULT_WEIGHT);
        } else {
            $urgency = Ticket::DEFAULT_WEIGHT;
            $impact = Ticket::DEFAULT_WEIGHT;
            $priority = Ticket::DEFAULT_WEIGHT;
        }

        if ($authorizer->isGranted('orga:update:tickets:status', $organization)) {
            $isResolved = $request->request->getBoolean('isResolved', false);
        } else {
            $isResolved = false;
        }

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $allUsers = $actorsLister->findByOrganization($organization);
        $agents = $actorsLister->findByOrganization($organization, roleType: 'agent');

        if (!$this->isCsrfTokenValid('create organization ticket', $csrfToken)) {
            return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                'organization' => $organization,
                'title' => $title,
                'message' => $messageContent,
                'type' => $type,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'isResolved' => $isResolved,
                'urgency' => $urgency,
                'impact' => $impact,
                'priority' => $priority,
                'allUsers' => $allUsers,
                'agents' => $agents,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $requester = ArrayHelper::find($allUsers, function ($user) use ($requesterUid): bool {
            return $user->getUid() === $requesterUid;
        });

        if (!$requester) {
            return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                'organization' => $organization,
                'title' => $title,
                'message' => $messageContent,
                'type' => $type,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'isResolved' => $isResolved,
                'urgency' => $urgency,
                'impact' => $impact,
                'priority' => $priority,
                'allUsers' => $allUsers,
                'agents' => $agents,
                'errors' => [
                    'requester' => $translator->trans('ticket.requester.invalid', [], 'errors'),
                ],
            ]);
        }

        $assignee = null;
        if ($assigneeUid) {
            $assignee = ArrayHelper::find($agents, function ($agent) use ($assigneeUid): bool {
                return $agent->getUid() === $assigneeUid;
            });
        }

        $ticket = new Ticket();
        $ticket->setTitle($title);
        $ticket->setType($type);
        $ticket->setUrgency($urgency);
        $ticket->setImpact($impact);
        $ticket->setPriority($priority);
        $ticket->setOrganization($organization);
        $ticket->setRequester($requester);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                'organization' => $organization,
                'title' => $title,
                'message' => $messageContent,
                'type' => $type,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'isResolved' => $isResolved,
                'urgency' => $urgency,
                'impact' => $impact,
                'priority' => $priority,
                'allUsers' => $allUsers,
                'agents' => $agents,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $message = new Message();
        $message->setContent($messageContent);
        $message->setTicket($ticket);
        $message->setIsConfidential(false);
        $message->setVia('webapp');

        $errors = $validator->validate($message);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                'organization' => $organization,
                'title' => $title,
                'message' => $messageContent,
                'type' => $type,
                'requesterUid' => $requesterUid,
                'assigneeUid' => $assigneeUid,
                'isResolved' => $isResolved,
                'urgency' => $urgency,
                'impact' => $impact,
                'priority' => $priority,
                'allUsers' => $allUsers,
                'agents' => $agents,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $ticketRepository->save($ticket, true);
        $messageRepository->save($message, true);

        $ticketEvent = new TicketEvent($ticket);
        $eventDispatcher->dispatch($ticketEvent, TicketEvent::CREATED);

        $messageEvent = new MessageEvent($message);
        $eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED);

        if ($assignee) {
            $ticket->setAssignee($assignee);
            $ticketRepository->save($ticket, true);

            $eventDispatcher->dispatch($ticketEvent, TicketEvent::ASSIGNED);
        }

        if ($isResolved) {
            $ticket->setStatus('resolved');
            $ticketRepository->save($ticket, true);

            $eventDispatcher->dispatch($ticketEvent, TicketEvent::RESOLVED);
        }

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
