<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Entity\EntityEvent>
 */
final class EntityEventFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        return [
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Entity\EntityEvent::class;
    }
}
