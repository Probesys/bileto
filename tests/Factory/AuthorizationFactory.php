<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Authorization;
use App\Utils\Random;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Authorization>
 */
final class AuthorizationFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        return [
            'uid' => Random::hex(20),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'role' => RoleFactory::new(),
            'holder' => UserFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }

    public static function class(): string
    {
        return Authorization::class;
    }
}
