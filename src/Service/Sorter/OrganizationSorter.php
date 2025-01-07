<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Organization;

class OrganizationSorter extends LocaleSorter
{
    /**
     * @param Organization[] $organizations
     */
    public function sort(array &$organizations): void
    {
        uasort($organizations, function (Organization $o1, Organization $o2): int {
            return $this->localeCompare($o1->getName(), $o2->getName());
        });
    }
}
