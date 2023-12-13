<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\ActivityMonitor;

/**
 * Allow to record the activity of an entity.
 *
 * @see RecordableEntitiesSubscriber
 * @see RecordableEntitiesTrait
 */
interface RecordableEntityInterface
{
    public function getId(): ?int;

    public function getEntityType(): string;
}
