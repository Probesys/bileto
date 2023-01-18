<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;

class OrganizationSorter extends LocaleSorter
{
    /**
     * @param Organization[] $organizations
     */
    public function sort(array &$organizations): void
    {
        $collator = new \Collator($this->getLocale());
        uasort($organizations, function (Organization $o1, Organization $o2) use ($collator) {
            $pathComparison = strcmp($o1->getParentsPath(), $o2->getParentsPath());
            if ($pathComparison !== 0) {
                return $pathComparison;
            } else {
                $nameComparison = $collator->compare($o1->getName(), $o2->getName());
                if ($nameComparison === false) {
                    $nameComparison = 0;
                }
                return $nameComparison;
            }
        });
    }
}
