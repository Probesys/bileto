<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Factory;

use App\Entity\MailboxEmail;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Webklex\PHPIMAP;

/**
 * @extends PersistentProxyObjectFactory<MailboxEmail>
 */
final class MailboxEmailFactory extends PersistentProxyObjectFactory
{
    /**
     * @return mixed[]
     */
    protected function defaults(): array
    {
        return [
            'date' => self::faker()->dateTime(),
            'from' => self::faker()->email(),
            'subject' => self::faker()->words(3, true),
            'htmlBody' => self::faker()->randomHtml(),
            'mailbox' => MailboxFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(function (array $attributes, string $class): MailboxEmail {
            $headers = <<<TEXT
                Subject: {$attributes['subject']}\r
                From: <{$attributes['from']}>\r
                To: support@example.com\r
                Date: {$attributes['date']->format(DATE_RFC1123)}\r
                Content-Type: text/html\r
                TEXT;

            if (isset($attributes['inReplyTo'])) {
                $headers .= "\nIn-Reply-To: <{$attributes['inReplyTo']}>\r";
            }

            $rawEmail = "{$headers}\n\r\n\r{$attributes['htmlBody']}";

            $clientManager = new PHPIMAP\ClientManager();
            $email = PHPIMAP\Message::fromString($rawEmail);

            $mailboxEmail = new MailboxEmail($attributes['mailbox'], $email);

            if (isset($attributes['lastError'])) {
                $mailboxEmail->setLastError($attributes['lastError']);
            }

            return $mailboxEmail;
        });
    }

    public static function class(): string
    {
        return MailboxEmail::class;
    }
}
