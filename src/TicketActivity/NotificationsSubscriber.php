<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
            MessageEvent::CREATED => 'sendMessageEmail',
            MessageEvent::CREATED_SOLUTION => 'sendMessageEmail',
            MessageEvent::APPROVED_SOLUTION => 'sendMessageEmail',
            MessageEvent::REFUSED_SOLUTION => 'sendMessageEmail',
        ];
    }

    public function __construct(
        private MessageBusInterface $bus,
    ) {
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
