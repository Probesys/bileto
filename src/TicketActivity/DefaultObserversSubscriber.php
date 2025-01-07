<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Repository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This subscriber automatically attaches observers of the ticket's
 * organization to the ticket.
 */
class DefaultObserversSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TicketEvent::CREATED => 'attachOrganizationObservers',
        ];
    }

    public function __construct(
        private Repository\TicketRepository $ticketRepository,
    ) {
    }

    public function attachOrganizationObservers(TicketEvent $event): void
    {
        $ticket = $event->getTicket();
        $organization = $ticket->getOrganization();

        $observers = $organization->getObservers();

        if (count($observers) === 0) {
            return;
        }

        foreach ($observers as $observer) {
            $ticket->addObserver($observer);
        }

        $this->ticketRepository->save($ticket, true);
    }
}
