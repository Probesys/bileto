<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;

class TicketTimeAccounting
{
    public function __construct(
        private ContractTimeAccounting $contractTimeAccounting,
        private Repository\TimeSpentRepository $timeSpentRepository,
    ) {
    }

    /**
     * Account the given time for the ticket.
     *
     * If the ticket has an ongoing contract, the time is accoutned for the
     * contract. Otherwise, an unaccounted time spent is created on the ticket.
     *
     * @param positive-int $minutes
     */
    public function accountTime(
        int $minutes,
        Entity\Ticket $ticket,
        ?Entity\Message $message = null,
    ): void {
        $contract = $ticket->getOngoingContract();

        if (!$contract) {
            $timeSpent = new Entity\TimeSpent();
            $timeSpent->setTicket($ticket);
            $timeSpent->setTime($minutes);
            $timeSpent->setRealTime($minutes);
            $timeSpent->setMessage($message);

            $this->timeSpentRepository->save($timeSpent, true);

            return;
        }

        $timeSpent = $this->contractTimeAccounting->accountTime($contract, $minutes);
        $timeSpent->setTicket($ticket);
        $timeSpent->setMessage($message);

        $this->timeSpentRepository->save($timeSpent, true);

        // Calculate the remaining time that is not accounted (i.e. because
        // there wasn't enough time in the contract).
        $remainingUnaccountedTime = $minutes - $timeSpent->getRealTime();

        if ($remainingUnaccountedTime > 0) {
            $timeSpent = new Entity\TimeSpent();
            $timeSpent->setTicket($ticket);
            $timeSpent->setTime($remainingUnaccountedTime);
            $timeSpent->setRealTime($remainingUnaccountedTime);
            $timeSpent->setMessage($message);

            $this->timeSpentRepository->save($timeSpent, true);
        }
    }
}
