<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\User;

class UserSorter extends LocaleSorter
{
    /**
     * @param User[] $users
     */
    public function sort(array &$users): void
    {
        $collator = new \Collator($this->getLocale());
        uasort($users, function (User $u1, User $u2) use ($collator) {
            $nameComparison = $collator->compare($u1->getDisplayName(), $u2->getDisplayName());
            if ($nameComparison === false) {
                $nameComparison = 0;
            }
            return $nameComparison;
        });
    }
}
