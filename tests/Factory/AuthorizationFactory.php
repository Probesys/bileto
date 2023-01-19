<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Authorization;
use App\Repository\AuthorizationRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Authorization>
 *
 * @method static Authorization|Proxy createOne(array $attributes = [])
 * @method static Authorization[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Authorization[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Authorization|Proxy find(object|array|mixed $criteria)
 * @method static Authorization|Proxy findOrCreate(array $attributes)
 * @method static Authorization|Proxy first(string $sortedField = 'id')
 * @method static Authorization|Proxy last(string $sortedField = 'id')
 * @method static Authorization|Proxy random(array $attributes = [])
 * @method static Authorization|Proxy randomOrCreate(array $attributes = [])
 * @method static Authorization[]|Proxy[] all()
 * @method static Authorization[]|Proxy[] findBy(array $attributes)
 * @method static Authorization[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Authorization[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static AuthorizationRepository|RepositoryProxy repository()
 * @method Authorization|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Authorization&Proxy createOne(array $attributes = [])
 * @phpstan-method static Authorization[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Authorization[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Authorization&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Authorization&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Authorization&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Authorization&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Authorization&Proxy random(array $attributes = [])
 * @phpstan-method static Authorization&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Authorization[]&Proxy[] all()
 * @phpstan-method static Authorization[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Authorization[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Authorization[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Authorization&Proxy create(array|callable $attributes = [])
 */
final class AuthorizationFactory extends ModelFactory
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
            'holder' => UserFactory::new(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Authorization::class;
    }
}
