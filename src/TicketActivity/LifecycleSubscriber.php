<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Repository\TicketRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handle the lifecycle of tickets.
 *
 * This class centralizes the updates of the tickets' status based on events
 * triggered by the rest of the application.
 */
class LifecycleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TicketEvent::ASSIGNED => 'processAssignedTicket',
            MessageEvent::CREATED => 'processAnswer',
            MessageEvent::CREATED_SOLUTION => 'processNewSolution',
            MessageEvent::APPROVED_SOLUTION => 'processApprovedSolution',
            MessageEvent::REFUSED_SOLUTION => 'processRefusedSolution',
        ];
    }

    public function __construct(
        private TicketRepository $ticketRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Pass a "new" ticket to "in progress" on a ticket is assigned.
     */
    public function processAssignedTicket(TicketEvent $event): void
    {
        $ticket = $event->getTicket();
        $status = $ticket->getStatus();
        $assignee = $ticket->getAssignee();

        if ($assignee !== null && $status === 'new') {
            $ticket->setStatus('in_progress');
            $this->ticketRepository->save($ticket, true);
        }
    }

    /**
     * Update the ticket's status when answering to the ticket.
     */
    public function processAnswer(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $ticket = $message->getTicket();

        $messageAuthor = $message->getCreatedBy();
        $isConfidential = $message->isConfidential();
        $requester = $ticket->getRequester();
        $assignee = $ticket->getAssignee();
        $status = $ticket->getStatus();

        if ($messageAuthor == $assignee) {
            if ($status === 'in_progress' && !$isConfidential) {
                $ticket->setStatus('pending');
                $this->ticketRepository->save($ticket, true);
            }
        } elseif ($messageAuthor == $requester) {
            if ($status === 'pending') {
                $ticket->setStatus('in_progress');
                $this->ticketRepository->save($ticket, true);
            } elseif ($status === 'resolved') {
                $ticket->setStatus('in_progress');
                $ticket->setSolution(null);
                $this->ticketRepository->save($ticket, true);
            }
        }
    }

    /**
     * Mark a ticket as resolved when a new solution is posted.
     */
    public function processNewSolution(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $ticket = $message->getTicket();

        if (
            $ticket->hasSolution() ||
            $ticket->getStatus() === 'closed' ||
            $message->isConfidential()
        ) {
            return;
        }

        $ticket->setSolution($message);
        $ticket->setStatus('resolved');

        $this->ticketRepository->save($ticket, true);

        $ticketEvent = new TicketEvent($ticket);
        $this->eventDispatcher->dispatch($ticketEvent, TicketEvent::RESOLVED);
    }

    /**
     * Close a ticket when a solution is approved.
     */
    public function processApprovedSolution(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $ticket = $message->getTicket();

        if ($ticket->getStatus() !== 'resolved') {
            return;
        }

        $ticket->setStatus('closed');

        $this->ticketRepository->save($ticket, true);
    }

    /**
     * Reopen a ticket when a solution is refused.
     */
    public function processRefusedSolution(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $ticket = $message->getTicket();

        if ($ticket->getStatus() !== 'resolved') {
            return;
        }

        $ticket->setStatus('in_progress');
        $ticket->setSolution(null);

        $this->ticketRepository->save($ticket, true);
    }
}
