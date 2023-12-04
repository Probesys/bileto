<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Authorization;

class AuthorizationSorter extends LocaleSorter
{
    /**
     * @param Authorization[] $authorizations
     */
    public function sort(array &$authorizations): void
    {
        uasort($authorizations, function (Authorization $a1, Authorization $a2): int {
            $roleComparison = $this->localeCompare(
                $a1->getRole()->getName(),
                $a2->getRole()->getName(),
            );

            if ($roleComparison !== 0) {
                return $roleComparison;
            }

            $orga1 = $a1->getOrganization();
            $orga2 = $a2->getOrganization();

            if ($orga1 === null) {
                return -1;
            }

            if ($orga2 === null) {
                return 1;
            }

            return $this->localeCompare($orga1->getName(), $orga2->getName());
        });
    }
}
