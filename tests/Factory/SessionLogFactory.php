<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Entity\SessionLog>
 */
final class SessionLogFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        return [
            'type' => self::faker()->randomElement(Entity\SessionLog::TYPES),
            'identifier' => self::faker()->safeEmail(),
            'ip' => self::faker()->ipv4(),
            'sessionId' => self::faker()->uuid(),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Entity\SessionLog::class;
    }
}
