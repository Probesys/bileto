<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Repository;
use App\Service;
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
            TicketEvent::TRANSFERRED => 'attachOngoingContract',
        ];
    }

    public function __construct(
        private Repository\ContractRepository $contractRepository,
        private Repository\TicketRepository $ticketRepository,
        private Repository\TimeSpentRepository $timeSpentRepository,
        private Service\ContractTimeAccounting $contractTimeAccounting,
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

        $ongoingContracts = $this->contractRepository->findOngoingByOrganization($organization);

        // Set the ongoing contract if there is one and only one ongoing
        // contract in the organization.
        if (count($ongoingContracts) !== 1) {
            return;
        }

        $ongoingContract = $ongoingContracts[0];

        $ticket->addContract($ongoingContract);
        $this->ticketRepository->save($ticket, true);

        // Account any unaccounted time spent on the contract (this can happen
        // during a transfer).
        $timeSpents = $ticket->getUnaccountedTimeSpents()->getValues();
        $this->contractTimeAccounting->accountTimeSpents($ongoingContract, $timeSpents);
        $this->timeSpentRepository->save($timeSpents, true);
    }
}
