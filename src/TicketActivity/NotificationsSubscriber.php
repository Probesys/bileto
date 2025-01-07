<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Message;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Send notifications on tickets events
 **/
class NotificationsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TicketEvent::CREATED => 'sendReceiptEmail',
            MessageEvent::CREATED => 'sendMessageEmail',
        ];
    }

    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Send a receipt by email to the requester of a ticket.
     */
    public function sendReceiptEmail(TicketEvent $event): void
    {
        $ticket = $event->getTicket();
        $this->bus->dispatch(new Message\SendReceiptEmail($ticket->getId()));
    }

    /**
     * Send the message by email to the different actors of the ticket.
     */
    public function sendMessageEmail(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $this->bus->dispatch(new Message\SendMessageEmail($message->getId()));
    }
}
