<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Contract;
use App\Entity\TimeSpent;

class ContractTimeAccounting
{
    /**
     * Create a TimeSpent associated to the given contract.
     *
     * The resulting TimeSpent will not necessarily have the exact $time
     * amount. For instance, if $time is greater than the time available in the
     * contract, the TimeSpent will only be accounted for the available time
     * (i.e. an additional TimeSpent must be created outside of this method).
     *
     * The time is expressed in minutes.
     */
    public function accountTime(Contract $contract, int $time): TimeSpent
    {
        $timeAccountingUnit = $contract->getTimeAccountingUnit();

        $timeAccounted = $this->calculateAccountedTime($time, $timeAccountingUnit);

        $timeSpent = new TimeSpent();
        $timeSpent->setTime($timeAccounted);
        $timeSpent->setRealTime($time);

        $contract->addTimeSpent($timeSpent);

        return $timeSpent;
    }

    /**
     * Try to associate TimeSpents with the given contract.
     *
     * TimeSpents are associated while there is enough time available in the
     * contract. Once a TimeSpent has more time than available time, it stops
     * even if the following TimeSpents have less time.
     *
     * @param TimeSpent[] $timeSpents
     */
    public function accountTimeSpents(Contract $contract, array $timeSpents): void
    {
        $timeAccountingUnit = $contract->getTimeAccountingUnit();

        foreach ($timeSpents as $timeSpent) {
            if ($timeSpent->mustNotBeAccounted()) {
                continue;
            }

            $timeAccounted = $this->calculateAccountedTime(
                $timeSpent->getRealTime(),
                $timeAccountingUnit,
            );

            $timeSpent->setTime($timeAccounted);

            $contract->addTimeSpent($timeSpent);
        }
    }

    /**
     * @param TimeSpent[] $timeSpents
     */
    public function unaccountTimeSpents(array $timeSpents): void
    {
        foreach ($timeSpents as $timeSpent) {
            $contract = $timeSpent->getContract();
            if ($contract) {
                $contract->removeTimeSpent($timeSpent);
            }

            $realTime = $timeSpent->getRealTime();
            $timeSpent->setTime($realTime);
        }
    }

    /**
     * Unaccount the given time spent from their current contract, and
     * reaccount them on the new specified contract.
     *
     * The time is be recalculated from the real time to make sure it's
     * coherent with the contract time accounting unit.
     *
     * Note that the new contract can be the same as the previous one.
     *
     * @param TimeSpent[] $timeSpents
     */
    public function reaccountTimeSpents(Contract $contract, array $timeSpents): void
    {
        $this->unaccountTimeSpents($timeSpents);
        $this->accountTimeSpents($contract, $timeSpents);
    }

    /**
     * Round up time to a multiplier of the time accounting unit (in the limit
     * of the available time).
     */
    private function calculateAccountedTime(int $time, int $timeAccountingUnit): int
    {
        if ($timeAccountingUnit > 0) {
            // If the time accounting unit is set, round up the time charged.
            return intval(ceil($time / $timeAccountingUnit)) * $timeAccountingUnit;
        } else {
            return $time;
        }
    }
}
