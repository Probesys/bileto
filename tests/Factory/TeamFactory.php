<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Team>
 *
 * @method static Team|Proxy createOne(array $attributes = [])
 * @method static Team[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Team[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Team|Proxy find(object|array|mixed $criteria)
 * @method static Team|Proxy findOrCreate(array $attributes)
 * @method static Team|Proxy first(string $sortedField = 'id')
 * @method static Team|Proxy last(string $sortedField = 'id')
 * @method static Team|Proxy random(array $attributes = [])
 * @method static Team|Proxy randomOrCreate(array $attributes = [])
 * @method static Team[]|Proxy[] all()
 * @method static Team[]|Proxy[] findBy(array $attributes)
 * @method static Team[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Team[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static TeamRepository|RepositoryProxy repository()
 * @method Team|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Team&Proxy createOne(array $attributes = [])
 * @phpstan-method static Team[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Team[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Team&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Team&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Team&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Team&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Team&Proxy random(array $attributes = [])
 * @phpstan-method static Team&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Team[]&Proxy[] all()
 * @phpstan-method static Team[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Team[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Team[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Team&Proxy create(array|callable $attributes = [])
 */
final class TeamFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'name' => self::faker()->words(3, true),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Team::class;
    }
}
