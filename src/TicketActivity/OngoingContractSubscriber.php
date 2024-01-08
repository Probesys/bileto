<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Repository\ContractRepository;
use App\Repository\TicketRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This subscriber attaches an ongoing Contract to a created ticket.
 */
class OngoingContractSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TicketEvent::CREATED => 'attachOngoingContract',
        ];
    }

    public function __construct(
        private ContractRepository $contractRepository,
        private TicketRepository $ticketRepository,
    ) {
    }

    public function attachOngoingContract(TicketEvent $event): void
    {
        $ticket = $event->getTicket();

        if ($ticket->getOngoingContract() !== null) {
            // Do nothing if the ticket has already an ongoing contract.
            return;
        }

        $organization = $ticket->getOrganization();

        $contracts = $this->contractRepository->findOngoingByOrganization($organization);

        // Set the ongoing contract if there is one and only one ongoing
        // contract in the organization.
        if (count($contracts) === 1) {
            $ticket->addContract($contracts[0]);
            $this->ticketRepository->save($ticket, true);
        }
    }
}
