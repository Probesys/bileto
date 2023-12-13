<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Uid;

/**
 * Allow to set the uid field of an entity.
 *
 * @see UidEntitiesSubscriber
 * @see UidEntityTrait
 */
interface UidEntityInterface
{
    public function getUid(): ?string;

    public function setUid(string $uid): self;
}
