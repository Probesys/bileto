<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Message;
use App\Entity\Ticket;
use App\Repository\MessageRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Utils\Time;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessagesController extends BaseController
{
    #[Route('/tickets/{uid}/messages/new', name: 'create ticket message', methods: ['POST'])]
    public function create(
        Ticket $ticket,
        Request $request,
        MessageRepository $messageRepository,
        OrganizationRepository $organizationRepository,
        TicketRepository $ticketRepository,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $messageContent */
        $messageContent = $request->request->get('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        /** @var boolean $isSolution */
        $isSolution = $request->request->getBoolean('isSolution', false);

        /** @var boolean $isConfidential */
        $isConfidential = $request->request->getBoolean('isConfidential', false);

        /** @var string $status */
        $status = $request->request->get('status', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $organization = $ticket->getOrganization();
        $parentOrganizations = $organizationRepository->findParents($organization);
        $organization->setParentOrganizations($parentOrganizations);

        $statuses = Ticket::getStatusesWithLabels();
        if ($ticket->getStatus() !== 'new') {
            unset($statuses['new']);
        }

        if (!$this->isCsrfTokenValid('create ticket message', $csrfToken)) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'messages' => $ticket->getMessages(),
                'organization' => $organization,
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'error' => $this->csrfError(),
            ]);
        }

        $message = new Message();
        $message->setContent($messageContent);
        $message->setCreatedAt(Time::now());
        $message->setCreatedBy($user);
        $message->setTicket($ticket);
        $message->setIsConfidential($isConfidential);
        $message->setVia('webapp');

        $errors = $validator->validate($message);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'messages' => $ticket->getMessages(),
                'organization' => $organization,
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        if ($message->isConfidential()) {
            $isSolution = false;
        }

        if ($ticket->isFinished()) {
            $status = $ticket->getStatus();
            $isSolution = false;
        }

        if ($isSolution) {
            $ticket->setSolution($message);
            $status = 'resolved';
        }

        $initialStatus = $ticket->getStatus();
        $ticket->setStatus($status);

        $errors = $validator->validate($ticket);
        if (count($errors) > 0) {
            $ticket->setStatus($initialStatus);
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'messages' => $ticket->getMessages(),
                'organization' => $organization,
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $messageRepository->save($message, true);
        $ticketRepository->save($ticket, true);

        return $this->redirectToRoute('ticket', [
            'uid' => $ticket->getUid(),
        ]);
    }
}
