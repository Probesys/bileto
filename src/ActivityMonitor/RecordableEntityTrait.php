<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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

    public function getEntityType(): string
    {
        // Don't use static::class or get_class as they may return a Doctrine
        // proxy class instead of the entity class!
        return self::class;
    }
}
