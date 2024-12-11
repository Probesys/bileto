<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
        $availableTime = $contract->getRemainingMinutes();
        $timeAccountingUnit = $contract->getTimeAccountingUnit();

        // If there is more spent time than time available in the contract, we
        // don't want to account the entire time. So we just account the
        // available time. Then, the remaining time will be set in a separated
        // TimeSpent.
        if ($time > $availableTime) {
            $time = $availableTime;
        }

        $timeAccounted = $this->calculateAccountedTime($time, $timeAccountingUnit, $availableTime);

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
        $availableTime = $contract->getRemainingMinutes();
        $timeAccountingUnit = $contract->getTimeAccountingUnit();

        foreach ($timeSpents as $timeSpent) {
            // If there is more spent time than time available in the contract,
            // we consider that we can't account more time so we stop here.
            if ($timeSpent->getRealTime() > $availableTime) {
                break;
            }

            $timeAccounted = $this->calculateAccountedTime(
                $timeSpent->getRealTime(),
                $timeAccountingUnit,
                $availableTime
            );

            $timeSpent->setTime($timeAccounted);

            $contract->addTimeSpent($timeSpent);

            $availableTime = $availableTime - $timeAccounted;
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
    private function calculateAccountedTime(int $time, int $timeAccountingUnit, int $availableTime): int
    {
        if ($timeAccountingUnit > 0) {
            // If the time accounting unit is set, round up the time charged.
            $timeAccounted = intval(ceil($time / $timeAccountingUnit)) * $timeAccountingUnit;
            // But keep it lower than the available time in the contract.
            // Note: this could be debated as, contractually, more time should
            // be charged. But in our case, it's how we handle the case.
            return min($timeAccounted, $availableTime);
        } else {
            return $time;
        }
    }
}
