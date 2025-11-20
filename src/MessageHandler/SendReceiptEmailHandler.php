<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Message;
use App\Repository;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class SendReceiptEmailHandler
{
    use EmailSender;

    public function __construct(
        private Repository\MessageRepository $messageRepository,
        private Repository\TicketRepository $ticketRepository,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private TransportInterface $transportInterface,
        #[Autowire(env: 'MAILER_FROM')]
        private string $mailerFrom,
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

        $createdBy = $ticket->getCreatedBy();
        $requester = $ticket->getRequester();

        if ($createdBy->getUid() !== $requester->getUid()) {
            // In this case, the requester already receives the "message"
            // notification, so we don't need to send the receipt email too.
            $this->logger->notice(
                "Receipt email of ticket {$ticketId} has not been sent as the requester did not created the ticket."
            );
            return;
        }

        if ($requester->isAnonymized()) {
            // Just to be clear: it should be impossible to set an anonymous
            // user as the requester of a new ticket. This is just an additionnal
            // check to be really really sure the mail is never sent.
            $this->logger->warning(
                "Receipt email of ticket {$ticketId} has not been sent as the requester is an anonymous user."
            );
            return;
        }

        $locale = $requester->getLocale();
        $subject = $this->translator->trans('emails.receipt.subject', locale: $locale);
        $subject = "[#{$ticket->getId()}] {$subject}";

        $email = new TemplatedEmail();
        $email->from($this->mailerFrom);
        $email->to($requester->getEmail());
        $email->subject($subject);
        $email->locale($locale);
        $email->context([
            'subject' => $subject,
            'ticket' => $ticket,
            'linkToBileto' => $requester->canLogin(),
        ]);
        $email->htmlTemplate('emails/receipt.html.twig');
        $email->textTemplate('emails/receipt.txt.twig');

        // Ask compliant autoresponders to not reply to this email
        $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');

        $emailId = $this->sendEmail($email);

        $message = $ticket->getMessages()->first();
        if ($message) {
            $message->addEmailNotificationReference($emailId);

            try {
                $this->messageRepository->save($message, true);
            } catch (EntityNotFoundException $e) {
                $this->logger->error(
                    "Message #{$message->getId()} notification reference cannot be saved: {$e->getMessage()}"
                );
            }
        }
    }
}
