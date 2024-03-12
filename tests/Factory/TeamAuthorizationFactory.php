<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\TeamAuthorization;
use App\Repository\TeamAuthorizationRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<TeamAuthorization>
 *
 * @method static TeamAuthorization|Proxy createOne(array $attributes = [])
 * @method static TeamAuthorization[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static TeamAuthorization[]|Proxy[] createSequence(array|callable $sequence)
 * @method static TeamAuthorization|Proxy find(object|array|mixed $criteria)
 * @method static TeamAuthorization|Proxy findOrCreate(array $attributes)
 * @method static TeamAuthorization|Proxy first(string $sortedField = 'id')
 * @method static TeamAuthorization|Proxy last(string $sortedField = 'id')
 * @method static TeamAuthorization|Proxy random(array $attributes = [])
 * @method static TeamAuthorization|Proxy randomOrCreate(array $attributes = [])
 * @method static TeamAuthorization[]|Proxy[] all()
 * @method static TeamAuthorization[]|Proxy[] findBy(array $attributes)
 * @method static TeamAuthorization[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static TeamAuthorization[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static TeamAuthorizationRepository|RepositoryProxy repository()
 * @method TeamAuthorization|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static TeamAuthorization&Proxy createOne(array $attributes = [])
 * @phpstan-method static TeamAuthorization[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static TeamAuthorization[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static TeamAuthorization&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static TeamAuthorization&Proxy findOrCreate(array $attributes)
 * @phpstan-method static TeamAuthorization&Proxy first(string $sortedField = 'id')
 * @phpstan-method static TeamAuthorization&Proxy last(string $sortedField = 'id')
 * @phpstan-method static TeamAuthorization&Proxy random(array $attributes = [])
 * @phpstan-method static TeamAuthorization&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static TeamAuthorization[]&Proxy[] all()
 * @phpstan-method static TeamAuthorization[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static TeamAuthorization[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static TeamAuthorization[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method TeamAuthorization&Proxy create(array|callable $attributes = [])
 */
final class TeamAuthorizationFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'uid' => Random::hex(20),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'role' => RoleFactory::new(),
            'team' => TeamFactory::new(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return TeamAuthorization::class;
    }
}
