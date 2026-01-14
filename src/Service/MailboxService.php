<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Security;
use Webklex\PHPIMAP;

class MailboxService
{
    public function __construct(
        private Security\Encryptor $encryptor,
    ) {
    }

    public static function getConfig(): PHPIMAP\Config
    {
        return PHPIMAP\Config::make([
            'decoding' => [
                'options' => [
                    'header' => 'iconv',
                    'message' => 'iconv',
                    'attachment' => 'iconv',
                ],
            ],
        ]);
    }

    public function getClient(Entity\Mailbox $mailbox): PHPIMAP\Client
    {
        $clientManager = new PHPIMAP\ClientManager(self::getConfig());
        return $clientManager->make([
            'host' => $mailbox->getHost(),
            'protocol' => $mailbox->getProtocol(),
            'port' => $mailbox->getPort(),
            'encryption' => $mailbox->getEncryption(),
            'validate_cert' => true,
            'username' => $mailbox->getUsername(),
            'password' => $this->encryptor->decrypt($mailbox->getPassword()),
        ]);
    }
}
