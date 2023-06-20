<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Entity\Mailbox;
use App\Entity\MailboxEmail;
use App\Message\FetchMailboxes;
use App\Repository\MailboxRepository;
use App\Repository\MailboxEmailRepository;
use App\Security\Encryptor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webklex\PHPIMAP;

#[AsMessageHandler]
class FetchMailboxesHandler
{
    public function __construct(
        private MailboxRepository $mailboxRepository,
        private MailboxEmailRepository $mailboxEmailRepository,
        private Encryptor $encryptor,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FetchMailboxes $message): void
    {
        $mailboxes = $this->mailboxRepository->findAll();
        foreach ($mailboxes as $mailbox) {
            $this->fetchMailbox($mailbox);
        }
    }

    protected function fetchMailbox(Mailbox $mailbox): void
    {
        $clientManager = new PHPIMAP\ClientManager();
        $client = $clientManager->make([
            'host' => $mailbox->getHost(),
            'protocol' => $mailbox->getProtocol(),
            'port' => $mailbox->getPort(),
            'encryption' => $mailbox->getEncryption(),
            'validate_cert' => true,
            'username' => $mailbox->getUsername(),
            'password' => $this->encryptor->decrypt($mailbox->getPassword()),
        ]);

        try {
            $client->connect();

            $folder = $client->getFolderByPath($mailbox->getFolder());
            $messages = $folder->messages()->unseen()->get();

            foreach ($messages as $email) {
                $mailboxEmail = new MailboxEmail($mailbox, $email);
                $this->mailboxEmailRepository->save($mailboxEmail, true);

                try {
                    $email->delete();
                } catch (\Exception $e) {
                    $this->logger->warning(
                        "Mailbox #{$mailbox->getId()} error (will try to mark as seen): {$e->getMessage()}"
                    );
                    $email->setFlag('Seen');
                }
            }

            $client->disconnect();
        } catch (\Exception $e) {
            $this->logger->error("Mailbox #{$mailbox->getId()} error: {$e->getMessage()}");
        }
    }
}
