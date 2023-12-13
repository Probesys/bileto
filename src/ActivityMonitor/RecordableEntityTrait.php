<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\ActivityMonitor;

/**
 * @see RecordableEntityInterface
 */
trait RecordableEntityTrait
{
    public function getId(): ?int
    {
        return $this->id;
    }
}
