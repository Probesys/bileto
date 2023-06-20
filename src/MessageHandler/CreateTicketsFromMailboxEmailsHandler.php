<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Entity\Ticket;
use App\Entity\MailboxEmail;
use App\Entity\Message;
use App\Message\CreateTicketsFromMailboxEmails;
use App\Repository\MailboxRepository;
use App\Repository\MailboxEmailRepository;
use App\Repository\MessageRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Security\Authentication\UserToken;
use App\Security\Encryptor;
use Psr\Log\LoggerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Webklex\PHPIMAP;

#[AsMessageHandler]
class CreateTicketsFromMailboxEmailsHandler
{
    public function __construct(
        private MailboxEmailRepository $mailboxEmailRepository,
        private MessageRepository $messageRepository,
        private TicketRepository $ticketRepository,
        private UserRepository $userRepository,
        private AccessDecisionManagerInterface $accessDecisionManager,
        private HtmlSanitizerInterface $appMessageSanitizer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateTicketsFromMailboxEmails $message): void
    {
        $mailboxEmails = $this->mailboxEmailRepository->findAll();

        foreach ($mailboxEmails as $mailboxEmail) {
            $senderEmail = $mailboxEmail->getFrom();

            $requester = $this->userRepository->findOneBy([
                'email' => $senderEmail,
            ]);

            if (!$requester) {
                $this->markError($mailboxEmail, 'unknown sender');
                continue;
            }

            $organization = $requester->getOrganization();

            if (!$organization) {
                $this->markError($mailboxEmail, 'sender is not attached to an organization');
                continue;
            }

            $token = new UserToken($requester);

            if (!$this->accessDecisionManager->decide($token, ['orga:create:tickets'], $organization)) {
                $this->markError($mailboxEmail, 'sender has not permission to create tickets');
                continue;
            }

            $subject = $mailboxEmail->getSubject();
            $messageContent = $this->appMessageSanitizer->sanitize($mailboxEmail->getBody());
            $date = $mailboxEmail->getDate();

            $ticket = new Ticket();
            $ticket->setCreatedAt($date);
            $ticket->setCreatedBy($requester);
            $ticket->setTitle($subject);
            $ticket->setType(Ticket::DEFAULT_TYPE);
            $ticket->setStatus(Ticket::DEFAULT_STATUS);
            $ticket->setUrgency(Ticket::DEFAULT_WEIGHT);
            $ticket->setImpact(Ticket::DEFAULT_WEIGHT);
            $ticket->setPriority(Ticket::DEFAULT_WEIGHT);
            $ticket->setOrganization($organization);
            $ticket->setRequester($requester);

            $message = new Message();
            $message->setCreatedAt($date);
            $message->setCreatedBy($requester);
            $message->setContent($messageContent);
            $message->setTicket($ticket);
            $message->setIsConfidential(false);
            $message->setVia('email');

            $this->ticketRepository->save($ticket, true);
            $this->messageRepository->save($message, true);
            $this->mailboxEmailRepository->remove($mailboxEmail, true);
        }
    }

    private function markError(MailboxEmail $mailboxEmail, string $error): void
    {
        $this->logger->warning("MailboxEmail {$mailboxEmail->getId()} cannot be imported: {$error}");

        $mailboxEmail->setLastError($error);
        $this->mailboxEmailRepository->save($mailboxEmail, true);
    }
}
