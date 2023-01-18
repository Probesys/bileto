<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Role;

class RoleSorter extends LocaleSorter
{
    /**
     * @param Role[] $roles
     */
    public function sort(array &$roles): void
    {
        $collator = new \Collator($this->getLocale());
        uasort($roles, function (Role $r1, Role $r2) use ($collator) {
            $nameComparison = $collator->compare($r1->getName(), $r2->getName());
            if ($nameComparison === false) {
                $nameComparison = 0;
            }
            return $nameComparison;
        });
    }
}
