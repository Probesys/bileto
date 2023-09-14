<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Contract;
use App\Repository\ContractRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Contract>
 *
 * @method static Contract|Proxy createOne(array $attributes = [])
 * @method static Contract[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Contract[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Contract|Proxy find(object|array|mixed $criteria)
 * @method static Contract|Proxy findOrCreate(array $attributes)
 * @method static Contract|Proxy first(string $sortedField = 'id')
 * @method static Contract|Proxy last(string $sortedField = 'id')
 * @method static Contract|Proxy random(array $attributes = [])
 * @method static Contract|Proxy randomOrCreate(array $attributes = [])
 * @method static Contract[]|Proxy[] all()
 * @method static Contract[]|Proxy[] findBy(array $attributes)
 * @method static Contract[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Contract[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static ContractRepository|RepositoryProxy repository()
 * @method Contract|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Contract&Proxy createOne(array $attributes = [])
 * @phpstan-method static Contract[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Contract[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Contract&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Contract&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Contract&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Contract&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Contract&Proxy random(array $attributes = [])
 * @phpstan-method static Contract&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Contract[]&Proxy[] all()
 * @phpstan-method static Contract[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Contract[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Contract[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Contract&Proxy create(array|callable $attributes = [])
 */
final class ContractFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        $duration = self::faker()->numberBetween(1, 9000);
        $startAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTime());
        $endAt = $startAt->modify("+{$duration} days");

        return [
            'uid' => Random::hex(20),
            'name' => self::faker()->words(3, true),
            'startAt' => $startAt,
            'endAt' => $endAt,
            'maxHours' => self::faker()->numberBetween(1, 9000),
            'notes' => '',
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Contract::class;
    }
}
