<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Team;

class TeamSorter extends LocaleSorter
{
    /**
     * @param Team[] $teams
     */
    public function sort(array &$teams): void
    {
        uasort($teams, function (Team $t1, Team $t2): int {
            return $this->localeCompare($t1->getName(), $t2->getName());
        });
    }
}
