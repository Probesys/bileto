<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Ticket;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\ActorsLister;
use App\Service\TicketSearcher;
use App\Utils\Time;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TicketsController extends BaseController
{
    #[Route('/organizations/{uid}/tickets', name: 'organization tickets', methods: ['GET', 'HEAD'])]
    public function index(
        Organization $organization,
        Request $request,
        OrganizationRepository $organizationRepository,
        TicketSearcher $ticketSearcher,
        UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $assigneeUid */
        $assigneeUid = $request->query->get('assignee', '');

        $ticketSearcher->setOrganization($organization);
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

        return $this->render('organizations/tickets/index.html.twig', [
            'organization' => $organization,
            'tickets' => $ticketSearcher->getTickets(),
            'countToAssign' => $ticketSearcher->countToAssign(),
            'countOwned' => $ticketSearcher->countAssignedTo($user),
            'currentPage' => $currentPage,
        ]);
    }

    #[Route('/organizations/{uid}/tickets/new', name: 'new organization ticket', methods: ['GET', 'HEAD'])]
    public function new(
        Organization $organization,
        ActorsLister $actorsLister,
        OrganizationRepository $organizationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets', $organization);

        $users = $actorsLister->listUsers();

        return $this->render('organizations/tickets/new.html.twig', [
            'organization' => $organization,
            'title' => '',
            'message' => '',
            'requesterId' => '',
            'assigneeId' => '',
            'status' => 'new',
            'statuses' => Ticket::getStatusesWithLabels(),
            'users' => $users,
        ]);
    }

    #[Route('/organizations/{uid}/tickets/new', name: 'create organization ticket', methods: ['POST'])]
    public function create(
        Organization $organization,
        Request $request,
        MessageRepository $messageRepository,
        OrganizationRepository $organizationRepository,
        TicketRepository $ticketRepository,
        UserRepository $userRepository,
        ActorsLister $actorsLister,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer
    ): Response {
        $this->denyAccessUnlessGranted('orga:create:tickets', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $title */
        $title = $request->request->get('title', '');

        /** @var string $messageContent */
        $messageContent = $request->request->get('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        /** @var int $requesterId */
        $requesterId = $request->request->getInt('requesterId', 0);

        /** @var int $assigneeId */
        $assigneeId = $request->request->getInt('assigneeId', 0);

        /** @var string $status */
        $status = $request->request->get('status', 'new');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $users = $actorsLister->listUsers();

        if (!$this->isCsrfTokenValid('create organization ticket', $csrfToken)) {
            return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                'organization' => $organization,
                'title' => $title,
                'message' => $messageContent,
                'requesterId' => $requesterId,
                'assigneeId' => $assigneeId,
                'status' => $status,
                'statuses' => Ticket::getStatusesWithLabels(),
                'users' => $users,
                'error' => $this->csrfError(),
            ]);
        }

        $requester = $userRepository->find($requesterId);
        if (!$requester) {
            return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                'organization' => $organization,
                'title' => $title,
                'message' => $messageContent,
                'requesterId' => $requesterId,
                'assigneeId' => $assigneeId,
                'status' => $status,
                'statuses' => Ticket::getStatusesWithLabels(),
                'users' => $users,
                'errors' => [
                    'requester' => new TranslatableMessage('The requester must exist.', [], 'validators'),
                ],
            ]);
        }

        if ($assigneeId) {
            $assignee = $userRepository->find($assigneeId);
            if (!$assignee) {
                return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                    'organization' => $organization,
                    'title' => $title,
                    'message' => $messageContent,
                    'requesterId' => $requesterId,
                    'assigneeId' => $assigneeId,
                    'status' => $status,
                    'statuses' => Ticket::getStatusesWithLabels(),
                    'users' => $users,
                    'errors' => [
                        'assignee' => new TranslatableMessage('The assignee must exist.', [], 'validators'),
                    ],
                ]);
            }
        } else {
            $assignee = null;
        }

        $ticket = new Ticket();
        $ticket->setTitle($title);
        $ticket->setStatus($status);
        $ticket->setOrganization($organization);

        $ticket->setRequester($requester);
        if ($assignee) {
            $ticket->setAssignee($assignee);
        }

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            return $this->renderBadRequest('organizations/tickets/new.html.twig', [
                'organization' => $organization,
                'title' => $title,
                'message' => $messageContent,
                'requesterId' => $requesterId,
                'assigneeId' => $assigneeId,
                'status' => $status,
                'statuses' => Ticket::getStatusesWithLabels(),
                'users' => $users,
                'errors' => $this->formatErrors($errors),
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
                'requesterId' => $requesterId,
                'assigneeId' => $assigneeId,
                'status' => $status,
                'statuses' => Ticket::getStatusesWithLabels(),
                'users' => $users,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $ticketRepository->save($ticket, true);
        $messageRepository->save($message, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
