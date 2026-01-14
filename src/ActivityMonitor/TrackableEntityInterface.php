<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\ActivityMonitor;

use App\Entity\User;

/**
 * Allow to track changes at en entity level (i.e. setting createdAt,
 * createdBy, updatedAt and updatedBy).
 *
 * @see TrackableEntitiesSubscriber
 * @see TrackableEntitiesTrait
 */
interface TrackableEntityInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function setCreatedAt(\DateTimeImmutable $createdAt): self;

    public function getCreatedBy(): ?User;

    public function setCreatedBy(?User $createdBy): self;

    public function isCreatedBy(User $user): bool;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self;

    public function getUpdatedBy(): ?User;

    public function setUpdatedBy(?User $updatedBy): self;

    public function isUpdatedBy(User $user): bool;
}
