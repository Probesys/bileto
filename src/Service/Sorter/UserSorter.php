<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\User;

class UserSorter extends LocaleSorter
{
    /**
     * @param User[] $users
     */
    public function sort(array &$users): void
    {
        uasort($users, function (User $u1, User $u2): int {
            return $this->localeCompare($u1->getDisplayName(), $u2->getDisplayName());
        });
    }
}
