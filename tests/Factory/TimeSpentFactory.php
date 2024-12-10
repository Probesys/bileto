<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\TimeSpent;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TimeSpent>
 */
final class TimeSpentFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        $user = UserFactory::new();
        $date = \DateTimeImmutable::createFromMutable(self::faker()->dateTime());

        return [
            'createdAt' => $date,
            'createdBy' => $user,
            'updatedAt' => $date,
            'updatedBy' => $user,
            'ticket' => TicketFactory::new(),
            'time' => self::faker()->numberBetween(1, 100),
            'realTime' => self::faker()->numberBetween(1, 100),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return TimeSpent::class;
    }
}
