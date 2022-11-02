<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Factory;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Ticket>
 *
 * @method static Ticket|Proxy createOne(array $attributes = [])
 * @method static Ticket[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Ticket[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Ticket|Proxy find(object|array|mixed $criteria)
 * @method static Ticket|Proxy findOrCreate(array $attributes)
 * @method static Ticket|Proxy first(string $sortedField = 'id')
 * @method static Ticket|Proxy last(string $sortedField = 'id')
 * @method static Ticket|Proxy random(array $attributes = [])
 * @method static Ticket|Proxy randomOrCreate(array $attributes = [])
 * @method static Ticket[]|Proxy[] all()
 * @method static Ticket[]|Proxy[] findBy(array $attributes)
 * @method static Ticket[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Ticket[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static TicketRepository|RepositoryProxy repository()
 * @method Ticket|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Ticket&Proxy createOne(array $attributes = [])
 * @phpstan-method static Ticket[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Ticket[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Ticket&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Ticket&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Ticket&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Ticket&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Ticket&Proxy random(array $attributes = [])
 * @phpstan-method static Ticket&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Ticket[]&Proxy[] all()
 * @phpstan-method static Ticket[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Ticket[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Ticket[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Ticket&Proxy create(array|callable $attributes = [])
 */
final class TicketFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'title' => self::faker()->text(),
            'uid' => Random::hex(20),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'createdBy' => UserFactory::new(),
            'requester' => UserFactory::new(),
            'organization' => OrganizationFactory::new(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Ticket::class;
    }
}
