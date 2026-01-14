<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Authorization;
use App\Entity\TeamAuthorization;

class AuthorizationSorter extends LocaleSorter
{
    /**
     * @template T of Authorization|TeamAuthorization
     *
     * @param array<T> $authorizations
     */
    public function sort(array &$authorizations): void
    {
        uasort($authorizations, function ($a1, $a2): int {
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
