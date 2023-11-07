<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\TimeSpent;
use App\Repository\TimeSpentRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<TimeSpent>
 *
 * @method static TimeSpent|Proxy createOne(array $attributes = [])
 * @method static TimeSpent[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static TimeSpent[]|Proxy[] createSequence(array|callable $sequence)
 * @method static TimeSpent|Proxy find(object|array|mixed $criteria)
 * @method static TimeSpent|Proxy findOrCreate(array $attributes)
 * @method static TimeSpent|Proxy first(string $sortedField = 'id')
 * @method static TimeSpent|Proxy last(string $sortedField = 'id')
 * @method static TimeSpent|Proxy random(array $attributes = [])
 * @method static TimeSpent|Proxy randomOrCreate(array $attributes = [])
 * @method static TimeSpent[]|Proxy[] all()
 * @method static TimeSpent[]|Proxy[] findBy(array $attributes)
 * @method static TimeSpent[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static TimeSpent[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static TimeSpentRepository|RepositoryProxy repository()
 * @method TimeSpent|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static TimeSpent&Proxy createOne(array $attributes = [])
 * @phpstan-method static TimeSpent[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static TimeSpent[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static TimeSpent&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static TimeSpent&Proxy findOrCreate(array $attributes)
 * @phpstan-method static TimeSpent&Proxy first(string $sortedField = 'id')
 * @phpstan-method static TimeSpent&Proxy last(string $sortedField = 'id')
 * @phpstan-method static TimeSpent&Proxy random(array $attributes = [])
 * @phpstan-method static TimeSpent&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static TimeSpent[]&Proxy[] all()
 * @phpstan-method static TimeSpent[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static TimeSpent[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static TimeSpent[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method TimeSpent&Proxy create(array|callable $attributes = [])
 */
final class TimeSpentFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'uid' => Random::hex(20),
            'ticket' => TicketFactory::new(),
            'time' => self::faker()->numberBetween(1, 100),
            'realTime' => self::faker()->numberBetween(1, 100),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return TimeSpent::class;
    }
}
