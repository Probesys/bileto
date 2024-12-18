<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Message;
use App\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class SendReceiptEmailHandler
{
    public function __construct(
        private Repository\MessageRepository $messageRepository,
        private Repository\TicketRepository $ticketRepository,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private TransportInterface $transportInterface,
    ) {
    }

    public function __invoke(Message\SendReceiptEmail $data): void
    {
        $ticketId = $data->getTicketId();
        $ticket = $this->ticketRepository->find($ticketId);

        if (!$ticket) {
            $this->logger->error("Ticket {$ticketId} cannot be found in SendReceiptEmailHandler.");
            return;
        }

        $requester = $ticket->getRequester();

        $locale = $requester->getLocale();
        $subject = $this->translator->trans('emails.receipt.subject', locale: $locale);
        $subject = "[#{$ticket->getId()}] {$subject}";

        $email = new TemplatedEmail();
        $email->to($requester->getEmail());
        $email->subject($subject);
        $email->locale($locale);
        $email->context([
            'subject' => $subject,
            'ticket' => $ticket,
        ]);
        $email->htmlTemplate('emails/receipt.html.twig');
        $email->textTemplate('emails/receipt.txt.twig');

        // Ask compliant autoresponders to not reply to this email
        $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');

        $sentEmail = $this->transportInterface->send($email);

        $message = $ticket->getMessages()->first();
        if ($message) {
            $emailId = $sentEmail->getMessageId();
            $message->addEmailNotificationReference($emailId);
            $this->messageRepository->save($message, true);
        }
    }
}
