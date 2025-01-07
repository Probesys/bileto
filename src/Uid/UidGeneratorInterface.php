<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Uid;

/**
 * Allow an entity repository to generate a unique UID for the corresponding
 * entity type.
 *
 * @see UidEntitiesSubscriber
 * @see UidGeneratorTrait
 */
interface UidGeneratorInterface
{
    public function generateUid(int $length = 20): string;
}
