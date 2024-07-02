<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Contract;
use App\Utils\Random;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Contract>
 */
final class ContractFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        $duration = self::faker()->numberBetween(1, 9000);
        $startAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTime());
        $endAt = $startAt->modify("+{$duration} days");

        return [
            'uid' => Random::hex(20),
            'organization' => OrganizationFactory::new(),
            'name' => self::faker()->words(3, true),
            'startAt' => $startAt,
            'endAt' => $endAt,
            'maxHours' => self::faker()->numberBetween(1, 9000),
            'timeAccountingUnit' => 0,
            'hoursAlert' => 0,
            'dateAlert' => 0,
            'notes' => '',
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Contract::class;
    }
}
