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
use App\Service\TicketTimeline;
use App\Utils\Time;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
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
        TicketTimeline $ticketTimeline,
        Security $security,
        ValidatorInterface $validator,
        HtmlSanitizerInterface $appMessageSanitizer
    ): Response {
        $organization = $ticket->getOrganization();
        $this->denyAccessUnlessGranted('orga:create:tickets:messages', $organization);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$ticket->hasActor($user)) {
            $this->denyAccessUnlessGranted('orga:see:tickets:all', $organization);
        }

        /** @var string $messageContent */
        $messageContent = $request->request->get('message', '');
        $messageContent = $appMessageSanitizer->sanitize($messageContent);

        /** @var boolean $isSolution */
        $isSolution = $request->request->getBoolean('isSolution', false);

        /** @var boolean $isConfidential */
        $isConfidential = $request->request->getBoolean('isConfidential', false);

        if ($security->isGranted('orga:update:tickets:status', $organization)) {
            /** @var string $status */
            $status = $request->request->get('status', '');
        } else {
            $status = $ticket->getStatus();
        }

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $statuses = Ticket::getStatusesWithLabels();
        if ($ticket->getStatus() !== 'new') {
            unset($statuses['new']);
        }

        if (!$this->isCsrfTokenValid('create ticket message', $csrfToken)) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'error' => $this->csrfError(),
            ]);
        }

        if ($isConfidential && !$security->isGranted('orga:create:tickets:messages:confidential', $organization)) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
                'message' => $messageContent,
                'status' => $status,
                'statuses' => $statuses,
                'isSolution' => $isSolution,
                'isConfidential' => $isConfidential,
                'errors' => [
                    'isConfidential' => new TranslatableMessage(
                        'You are not authorized to answer confidentially.',
                        [],
                        'errors',
                    )
                ],
            ]);
        }

        $message = new Message();
        $message->setContent($messageContent);
        $message->setTicket($ticket);
        $message->setIsConfidential($isConfidential);
        $message->setVia('webapp');

        $errors = $validator->validate($message);
        if (count($errors) > 0) {
            return $this->renderBadRequest('tickets/show.html.twig', [
                'ticket' => $ticket,
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
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
                'timeline' => $ticketTimeline->build($ticket),
                'organization' => $organization,
                'today' => Time::relative('today'),
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
