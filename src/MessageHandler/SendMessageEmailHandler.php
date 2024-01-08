<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Repository\MessageRepository;
use App\Message\SendMessageEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class SendMessageEmailHandler
{
    public function __construct(
        private MessageRepository $messageRepository,
        private TransportInterface $transportInterface,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendMessageEmail $data): void
    {
        $messageId = $data->getMessageId();
        $message = $this->messageRepository->find($messageId);

        if (!$message) {
            $this->logger->error("Message {$messageId} cannot be found in SendMessageEmailHandler.");
            return;
        }

        if ($message->isConfidential()) {
            // If the message is confidential, it should not be sent to recipients
            // who don't have the orga:see:tickets:messages:confidential
            // permission. At the moment, I don't know what's the best solution
            // to handle that so I've decided to never send the notification in
            // that case. This will have to be improved in the future.
            return;
        }

        $ticket = $message->getTicket();

        $author = $message->getCreatedBy();
        $requester = $ticket->getRequester();
        $assignee = $ticket->getAssignee();

        $recipients = [];

        if ($requester !== $author) {
            $recipients[] = $requester->getEmail();
        }

        if ($assignee && $assignee !== $author) {
            $recipients[] = $assignee->getEmail();
        }

        $recipients = array_unique($recipients);

        if (empty($recipients)) {
            return;
        }

        $subject = "Re: [#{$ticket->getId()}] {$ticket->getTitle()}";

        $email = new Email();
        $email->bcc(...$recipients);
        $email->subject($subject);
        $content = $message->getContent();
        $email->html($content);
        $email->text(strip_tags($content));

        $sentEmail = $this->transportInterface->send($email);

        $emailId = $sentEmail->getMessageId();
        $message->setEmailId($emailId);
        $this->messageRepository->save($message, true);
    }
}
