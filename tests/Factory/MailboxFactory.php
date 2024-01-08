<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Mailbox;
use App\Repository\MailboxRepository;
use App\Security\Encryptor;
use App\Utils\Random;
use Zenstruck\Foundry\RepositoryProxy;
use Zenstruck\Foundry\ModelFactory;
use Zenstruck\Foundry\Proxy;

/**
 * @extends ModelFactory<Mailbox>
 *
 * @method static Mailbox|Proxy createOne(array $attributes = [])
 * @method static Mailbox[]|Proxy[] createMany(int $number, array|callable $attributes = [])
 * @method static Mailbox[]|Proxy[] createSequence(array|callable $sequence)
 * @method static Mailbox|Proxy find(object|array|mixed $criteria)
 * @method static Mailbox|Proxy findOrCreate(array $attributes)
 * @method static Mailbox|Proxy first(string $sortedField = 'id')
 * @method static Mailbox|Proxy last(string $sortedField = 'id')
 * @method static Mailbox|Proxy random(array $attributes = [])
 * @method static Mailbox|Proxy randomOrCreate(array $attributes = [])
 * @method static Mailbox[]|Proxy[] all()
 * @method static Mailbox[]|Proxy[] findBy(array $attributes)
 * @method static Mailbox[]|Proxy[] randomSet(int $number, array $attributes = [])
 * @method static Mailbox[]|Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @method static MailboxRepository|RepositoryProxy repository()
 * @method Mailbox|Proxy create(array|callable $attributes = [])
 *
 * @phpstan-method static Mailbox&Proxy createOne(array $attributes = [])
 * @phpstan-method static Mailbox[]&Proxy[] createMany(int $number, array|callable $attributes = [])
 * @phpstan-method static Mailbox[]&Proxy[] createSequence(array|callable $sequence)
 * @phpstan-method static Mailbox&Proxy find(object|array|mixed $criteria)
 * @phpstan-method static Mailbox&Proxy findOrCreate(array $attributes)
 * @phpstan-method static Mailbox&Proxy first(string $sortedField = 'id')
 * @phpstan-method static Mailbox&Proxy last(string $sortedField = 'id')
 * @phpstan-method static Mailbox&Proxy random(array $attributes = [])
 * @phpstan-method static Mailbox&Proxy randomOrCreate(array $attributes = [])
 * @phpstan-method static Mailbox[]&Proxy[] all()
 * @phpstan-method static Mailbox[]&Proxy[] findBy(array $attributes)
 * @phpstan-method static Mailbox[]&Proxy[] randomSet(int $number, array $attributes = [])
 * @phpstan-method static Mailbox[]&Proxy[] randomRange(int $min, int $max, array $attributes = [])
 * @phpstan-method Mailbox&Proxy create(array|callable $attributes = [])
 */
final class MailboxFactory extends ModelFactory
{
    public function __construct(
        private Encryptor $encryptor,
    ) {
        parent::__construct();
    }

    /**
     * @return mixed[]
     */
    protected function getDefaults(): array
    {
        return [
            'uid' => Random::hex(20),
            'name' => self::faker()->words(3, true),
            'host' => self::faker()->domainName(),
            'protocol' => 'imap',
            'port' => self::faker()->randomElement([143, 993]),
            'encryption' => self::faker()->randomElement(['tls', 'ssl', 'none']),
            'username' => self::faker()->userName(),
            'password' => self::faker()->password(),
            'authentication' => 'normal',
            'folder' => 'INBOX',
        ];
    }

    protected function initialize(): self
    {
        return $this->afterInstantiate(function (Mailbox $mailbox): void {
            $encryptedPassword = $this->encryptor->encrypt($mailbox->getPassword());
            $mailbox->setPassword($encryptedPassword);
        });
    }

    protected static function getClass(): string
    {
        return Mailbox::class;
    }
}
