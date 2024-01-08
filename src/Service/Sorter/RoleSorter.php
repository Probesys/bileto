<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Role;

class RoleSorter extends LocaleSorter
{
    /**
     * @param Role[] $roles
     */
    public function sort(array &$roles): void
    {
        uasort($roles, function (Role $r1, Role $r2): int {
            return $this->localeCompare($r1->getName(), $r2->getName());
        });
    }
}
