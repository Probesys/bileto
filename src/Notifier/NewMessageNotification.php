<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Notifier;

use App\Entity\Message;
use App\Entity\Ticket;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class NewMessageNotification extends Notification implements EmailNotificationInterface
{
    public function __construct(Message $message)
    {
        $ticket = $message->getTicket();
        $subject = "Re: [#{$ticket->getId()}] {$ticket->getTitle()}";

        parent::__construct($subject, ['email']);

        $this->content($message->getContent());
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $email = NotificationEmail::asPublicEmail();
        $email->to($recipient->getEmail());
        $email->subject($this->getSubject());
        $email->content($this->getContent(), true);

        return new EmailMessage($email);
    }
}
