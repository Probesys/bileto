<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
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
        ];
    }

    public function __construct(
        private MessageDocumentRepository $messageDocumentRepository,
    ) {
    }

    public function attachMessageDocuments(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $ticket = $message->getTicket();

        // We consider a ticket to be new if it and the message were both
        // created within the same time window (arbitrarily set to 2 seconds
        // which should be way enough).
        $ticketCreatedAt = $ticket->getCreatedAt()->getTimestamp();
        $messageCreatedAt = $message->getCreatedAt()->getTimestamp();
        $isNewTicket = abs($ticketCreatedAt - $messageCreatedAt) <= 2;

        $context = "ticket-{$ticket->getUid()}";

        if ($isNewTicket) {
            // The ticket has just been created: we must use the context of the
            // organization as the ticket UID wasn't known when uploading the
            // files.
            $organization = $ticket->getOrganization();
            $context = "organization-{$organization->getUid()}";
        }

        // Fetch all the unattached message documents of the author of the message.
        $messageDocuments = $this->messageDocumentRepository->findBy([
            'createdBy' => $message->getCreatedBy(),
            'context' => $context,
            'message' => null,
        ]);

        foreach ($messageDocuments as $messageDocument) {
            $messageDocument->setMessage($message);
        }

        $this->messageDocumentRepository->save($messageDocuments, true);
    }
}
