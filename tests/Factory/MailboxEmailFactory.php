<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\MailboxEmail;
use App\Repository\MailboxEmailRepository;
use App\Security\Encryptor;
use App\Utils\Random;
use Zenstruck\Foundry\Instantiator;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;
use Webklex\PHPIMAP;

/**
 * @extends ModelFactory<MailboxEmail>
 *
 * @method static MailboxEmail|Proxy createOne(array $attributes = [])
 * @method static MailboxEmail[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static MailboxEmail[]|Proxy[] createSequence(array|callable $sequence)
 * @method static MailboxEmail|Proxy find(object|array|mixed $criteria)
 * @method static MailboxEmail|Proxy findOrCreate(array $attributes)
 * @method static MailboxEmail|Proxy first(string $sortedField = 'id')
 * @method static MailboxEmail|Proxy last(string $sortedField = 'id')
 * @method static MailboxEmail|Proxy random(array $attributes = [])
 * @method static MailboxEmail|Proxy randomOrCreate(array $attributes = [])
 * @method static MailboxEmail[]|Proxy[] all()
 * @method static MailboxEmail[]|Proxy[] findBy(array $attributes)
 * @method static MailboxEmail[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static MailboxEmail[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MailboxEmailRepository|RepositoryProxy repository()
 * @method MailboxEmail|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static MailboxEmail&Proxy createOne(array $attributes = [])
 * @phpstan-method static MailboxEmail[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static MailboxEmail[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static MailboxEmail&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static MailboxEmail&Proxy findOrCreate(array $attributes)
 * @phpstan-method static MailboxEmail&Proxy first(string $sortedField = 'id')
 * @phpstan-method static MailboxEmail&Proxy last(string $sortedField = 'id')
 * @phpstan-method static MailboxEmail&Proxy random(array $attributes = [])
 * @phpstan-method static MailboxEmail&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static MailboxEmail[]&Proxy[] all()
 * @phpstan-method static MailboxEmail[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static MailboxEmail[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static MailboxEmail[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method MailboxEmail&Proxy create(array|callable $attributes = [])
 */
final class MailboxEmailFactory extends ModelFactory
{
    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'date' => self::faker()->dateTime(),
            'from' => self::faker()->email(),
            'subject' => self::faker()->words(3, true),
            'htmlBody' => self::faker()->randomHtml(),
            'mailbox' => MailboxFactory::new(),
        ];
    }

    protected function initialize(): self
    {
        return $this->instantiateWith(function (array $attributes, string $class): MailboxEmail {
            $rawEmail = <<<TEXT
                Subject: {$attributes['subject']}\r
                From: <{$attributes['from']}>\r
                To: support@example.com\r
                Date: {$attributes['date']->format(DATE_RFC1123)}\r
                Content-Type: text/html\r
                \r
                \r
                {$attributes['htmlBody']}

                TEXT;

            $clientManager = new PHPIMAP\ClientManager();
            $email = PHPIMAP\Message::fromString($rawEmail);

            return new MailboxEmail($attributes['mailbox'], $email);
        });
    }

    protected static function getClass(): string
    {
        return MailboxEmail::class;
    }
}
