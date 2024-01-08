<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Role>
 *
 * @method static Role|Proxy createOne(array $attributes = [])
 * @method static Role[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Role[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Role|Proxy find(object|array|mixed $criteria)
 * @method static Role|Proxy findOrCreate(array $attributes)
 * @method static Role|Proxy first(string $sortedField = 'id')
 * @method static Role|Proxy last(string $sortedField = 'id')
 * @method static Role|Proxy random(array $attributes = [])
 * @method static Role|Proxy randomOrCreate(array $attributes = [])
 * @method static Role[]|Proxy[] all()
 * @method static Role[]|Proxy[] findBy(array $attributes)
 * @method static Role[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Role[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static RoleRepository|RepositoryProxy repository()
 * @method Role|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Role&Proxy createOne(array $attributes = [])
 * @phpstan-method static Role[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Role[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Role&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Role&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Role&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Role&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Role&Proxy random(array $attributes = [])
 * @phpstan-method static Role&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Role[]&Proxy[] all()
 * @phpstan-method static Role[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Role[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Role[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Role&Proxy create(array|callable $attributes = [])
 */
final class RoleFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'uid' => Random::hex(20),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'name' => self::faker()->words(3, true),
            'description' => self::faker()->text(),
            'type' => self::faker()->randomElement(Role::TYPES),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Role::class;
    }
}
