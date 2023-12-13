<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\ActivityMonitor;

use App\Entity\User;

/**
 * Allow to monitor an entity.
 *
 * Monitoring is done in two ways:
 *
 * - tracking changes at the entity level (i.e. setting createdAt, createdBy,
 *   updatedAt and updatedBy)
 * - recording the activity of the entity via EntityEvent entities
 *
 * @see RecordableEntitiesSubscriber
 * @see TrackableEntitiesSubscriber
 */
interface MonitorableEntityInterface extends RecordableEntityInterface, TrackableEntityInterface
{
}
