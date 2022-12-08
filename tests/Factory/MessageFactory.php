<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Message>
 *
 * @method static Message|Proxy createOne(array $attributes = [])
 * @method static Message[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Message[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Message|Proxy find(object|array|mixed $criteria)
 * @method static Message|Proxy findOrCreate(array $attributes)
 * @method static Message|Proxy first(string $sortedField = 'id')
 * @method static Message|Proxy last(string $sortedField = 'id')
 * @method static Message|Proxy random(array $attributes = [])
 * @method static Message|Proxy randomOrCreate(array $attributes = [])
 * @method static Message[]|Proxy[] all()
 * @method static Message[]|Proxy[] findBy(array $attributes)
 * @method static Message[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Message[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MessageRepository|RepositoryProxy repository()
 * @method Message|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Message&Proxy createOne(array $attributes = [])
 * @phpstan-method static Message[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Message[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Message&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Message&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Message&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Message&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Message&Proxy random(array $attributes = [])
 * @phpstan-method static Message&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Message[]&Proxy[] all()
 * @phpstan-method static Message[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Message[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Message[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Message&Proxy create(array|callable $attributes = [])
 */
final class MessageFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'createdBy' => UserFactory::new(),
            'content' => self::faker()->text(),
            'ticket' => TicketFactory::new(),
        ];
    }

    protected function initialize(): self
    {
        return $this;
    }

    protected static function getClass(): string
    {
        return Message::class;
    }
}
