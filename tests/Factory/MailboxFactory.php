<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\Mailbox;
use App\Security\Encryptor;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Mailbox>
 */
final class MailboxFactory extends PersistentProxyObjectFactory
{
    public function __construct(
        private Encryptor $encryptor,
    ) {
        parent::__construct();
    }

    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        return [
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

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (Mailbox $mailbox): void {
            $encryptedPassword = $this->encryptor->encrypt($mailbox->getPassword());
            $mailbox->setPassword($encryptedPassword);
        });
    }

    public static function class(): string
    {
        return Mailbox::class;
    }
}
