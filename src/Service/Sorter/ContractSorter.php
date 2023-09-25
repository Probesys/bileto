<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Contract;

class ContractSorter extends LocaleSorter
{
    /**
     * @param Contract[] $contracts
     */
    public function sort(array &$contracts): void
    {
        uasort($contracts, function (Contract $c1, Contract $c2) {
            $endAtDiff = $c2->getEndAt()->getTimestamp() - $c1->getEndAt()->getTimestamp();

            if ($endAtDiff === 0) {
                return $this->localeCompare($c1->getName(), $c2->getName());
            }

            return $endAtDiff;
        });
    }
}
