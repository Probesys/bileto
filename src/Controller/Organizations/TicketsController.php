<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Message;
use App\Entity\Organization;
use App\Entity\Ticket;
use App\Repository\MessageRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
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
        TicketSearcher $ticketSearcher,
        UserRepository $userRepository,
    ): Response {
        /** @var string $assigneeUid */
        $assigneeUid = $request->query->get('assignee', '');

        $ticketSearcher->setOrganization($organization);
        $ticketSearcher->setStatus(Ticket::OPEN_STATUSES);

        if ($assigneeUid === 'none') {
            $ticketSearcher->setAssignee(null);
            $currentPage = 'to assign';
        } elseif ($assigneeUid !== '') {
            $assignee = $userRepository->findOneBy(['uid' => $assigneeUid]);
            $ticketSearcher->setAssignee($assignee);
            $currentPage = 'owned';
        } else {
            $currentPage = 'all';
        }

        return $this->render('organizations/tickets/index.html.twig', [
            'organization' => $organization,
            'tickets' => $ticketSearcher->getTickets(),
            'currentPage' => $currentPage,
        ]);
    }

    #[Route('/organizations/{uid}/tickets/new', name: 'new organization ticket', methods: ['GET', 'HEAD'])]
    public function new(
        Organization $organization,
        UserRepository $userRepository
    ): Response {
        $users = $userRepository->findBy([], ['email' => 'ASC']);
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
        TicketRepository $ticketRepository,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $title */
        $title = $request->request->get('title', '');

        /** @var string $messageContent */
        $messageContent = $request->request->get('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        /** @var string $requesterId */
        $requesterId = $request->request->get('requesterId', '');

        /** @var string $assigneeId */
        $assigneeId = $request->request->get('assigneeId', '');

        /** @var string $status */
        $status = $request->request->get('status', 'new');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $users = $userRepository->findBy([], ['email' => 'ASC']);

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
                    'requester' => new TranslatableMessage('The requester must exist.'),
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
                        'assignee' => new TranslatableMessage('The assignee must exist.'),
                    ],
                ]);
            }
        } else {
            $assignee = null;
        }

        $ticket = new Ticket();
        $ticket->setTitle($title);
        $ticket->setStatus($status);

        $uid = $ticketRepository->generateUid();
        $ticket->setUid($uid);

        $ticket->setCreatedAt(Time::now());
        $ticket->setCreatedBy($user);
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
        $message->setCreatedAt(Time::now());
        $message->setCreatedBy($user);
        $message->setTicket($ticket);
        $message->setIsPrivate(false);
        $message->setIsSolution(false);
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
