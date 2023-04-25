<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Organization>
 *
 * @method static Organization|Proxy createOne(array $attributes = [])
 * @method static Organization[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Organization[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Organization|Proxy find(object|array|mixed $criteria)
 * @method static Organization|Proxy findOrCreate(array $attributes)
 * @method static Organization|Proxy first(string $sortedField = 'id')
 * @method static Organization|Proxy last(string $sortedField = 'id')
 * @method static Organization|Proxy random(array $attributes = [])
 * @method static Organization|Proxy randomOrCreate(array $attributes = [])
 * @method static Organization[]|Proxy[] all()
 * @method static Organization[]|Proxy[] findBy(array $attributes)
 * @method static Organization[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Organization[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static OrganizationRepository|RepositoryProxy repository()
 * @method Organization|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Organization&Proxy createOne(array $attributes = [])
 * @phpstan-method static Organization[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Organization[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Organization&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Organization&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Organization&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Organization&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Organization&Proxy random(array $attributes = [])
 * @phpstan-method static Organization&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Organization[]&Proxy[] all()
 * @phpstan-method static Organization[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Organization[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Organization[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Organization&Proxy create(array|callable $attributes = [])
 */
final class OrganizationFactory extends ModelFactory
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
            'parentsPath' => '/',
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Organization::class;
    }
}
