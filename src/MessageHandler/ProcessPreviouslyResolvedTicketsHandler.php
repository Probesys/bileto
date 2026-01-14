<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Message;
use App\Repository;
use App\TicketActivity;
use App\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessPreviouslyResolvedTicketsHandler
{
    public function __construct(
        private Repository\TicketRepository $ticketRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Message\ProcessPreviouslyResolvedTickets $message): void
    {
        $oneWeekAgo = Utils\Time::ago(7, 'days');
        $tickets = $this->ticketRepository->findResolvedOlderThan($oneWeekAgo);

        $countTickets = count($tickets);

        if ($countTickets > 0) {
            $this->logger->notice(
                "[ProcessPreviouslyResolvedTickets] {$countTickets} tickets resolved one week ago to approve"
            );

            foreach ($tickets as $ticket) {
                $ticketEvent = new TicketActivity\TicketEvent($ticket);
                $this->eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::APPROVED);
            }
        }
    }
}
