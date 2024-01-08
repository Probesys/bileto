<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Uid;

use App\Utils\Random;

/**
 * @see UidGeneratorInterface
 */
trait UidGeneratorTrait
{
    public function generateUid(int $length = 20): string
    {
        while (true) {
            $uid = Random::hex($length);

            // This `if` condition doesn't protect from parallel requests being
            // made at the same time. Thus, there's a very little possibility
            // that we call `save()` with a uid that is already in the
            // database. Fortunately, the database has a `UNIQUE` constraint
            // and the request would fail.
            if ($this->findOneBy(['uid' => $uid]) === null) {
                return $uid;
            }
        }
    }
}
