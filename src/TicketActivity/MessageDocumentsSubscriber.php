<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Repository\MessageDocumentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This subscriber attaches unattached MessageDocuments to a created message.
 */
class MessageDocumentsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::CREATED => 'attachMessageDocuments',
            MessageEvent::CREATED_SOLUTION => 'attachMessageDocuments',
        ];
    }

    public function __construct(
        private MessageDocumentRepository $messageDocumentRepository,
    ) {
    }

    public function attachMessageDocuments(MessageEvent $event): void
    {
        $message = $event->getMessage();

        // Fetch all the unattached message documents of the author of the message.
        $messageDocuments = $this->messageDocumentRepository->findBy([
            'createdBy' => $message->getCreatedBy(),
            'message' => null,
        ]);

        foreach ($messageDocuments as $messageDocument) {
            $messageDocument->setMessage($message);
        }

        $this->messageDocumentRepository->saveBatch($messageDocuments, true);
    }
}
