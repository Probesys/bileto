<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Contract;
use App\Entity\TimeSpent;

class ContractBilling
{
    /**
     * Create a TimeSpent associated to the given contract.
     *
     * The resulting TimeSpent will not necessarily have the exact $time
     * amount. For instance, if $time is greater than the time available in the
     * contract, the TimeSpent will only be charged with the available time
     * (i.e. an additional TimeSpent must be created outside of this method).
     *
     * The time is expressed in minutes.
     */
    public function chargeTime(Contract $contract, int $time): TimeSpent
    {
        $availableTime = $contract->getRemainingMinutes();
        $billingInterval = $contract->getBillingInterval();

        // If there is more spent time than time available in the contract, we
        // don't want to charge the entire time. So we just charge the
        // available time. Then, the remaining time will be set in a separated
        // uncharged TimeSpent.
        if ($time > $availableTime) {
            $time = $availableTime;
        }

        $timeCharged = $this->calculateChargedTime($time, $billingInterval, $availableTime);

        $timeSpent = new TimeSpent();
        $timeSpent->setTime($timeCharged);
        $timeSpent->setRealTime($time);
        $timeSpent->setContract($contract);

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
    public function chargeTimeSpents(Contract $contract, array $timeSpents): void
    {
        $availableTime = $contract->getRemainingMinutes();
        $billingInterval = $contract->getBillingInterval();

        foreach ($timeSpents as $timeSpent) {
            // If there is more spent time than time available in the contract,
            // we consider that we can't charge more time so we stop here.
            if ($timeSpent->getRealTime() > $availableTime) {
                break;
            }

            $timeCharged = $this->calculateChargedTime($timeSpent->getRealTime(), $billingInterval, $availableTime);

            $timeSpent->setTime($timeCharged);
            $timeSpent->setContract($contract);

            $availableTime = $availableTime - $timeCharged;
        }
    }

    /**
     * Round up time to a multiplier of billing interval (in the limit of the
     * available time).
     */
    private function calculateChargedTime(int $time, int $billingInterval, int $availableTime): int
    {
        if ($billingInterval > 0) {
            // If the billing interval is set, round up the time charged.
            $timeCharged = intval(ceil($time / $billingInterval)) * $billingInterval;
            // But keep it lower than the available time in the contract.
            // Note: this could be debated as, contractually, more time should
            // be charged. But in our case, it's how we handle the case.
            return min($timeCharged, $availableTime);
        } else {
            return $time;
        }
    }
}
