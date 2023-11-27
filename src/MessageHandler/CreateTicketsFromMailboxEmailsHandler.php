<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Entity\MailboxEmail;
use App\Entity\Message;
use App\Entity\MessageDocument;
use App\Entity\Ticket;
use App\Message\CreateTicketsFromMailboxEmails;
use App\Message\SendMessageEmail;
use App\Repository\ContractRepository;
use App\Repository\MailboxRepository;
use App\Repository\MailboxEmailRepository;
use App\Repository\MessageRepository;
use App\Repository\MessageDocumentRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Security\Authentication\UserToken;
use App\Security\Encryptor;
use App\Service\MessageDocumentStorage;
use App\Service\MessageDocumentStorageError;
use Psr\Log\LoggerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Webklex\PHPIMAP;

#[AsMessageHandler]
class CreateTicketsFromMailboxEmailsHandler
{
    public function __construct(
        private ContractRepository $contractRepository,
        private MailboxEmailRepository $mailboxEmailRepository,
        private MessageRepository $messageRepository,
        private MessageDocumentRepository $messageDocumentRepository,
        private MessageDocumentStorage $messageDocumentStorage,
        private TicketRepository $ticketRepository,
        private UserRepository $userRepository,
        private AccessDecisionManagerInterface $accessDecisionManager,
        private HtmlSanitizerInterface $appMessageSanitizer,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateTicketsFromMailboxEmails $message): void
    {
        $mailboxEmails = $this->mailboxEmailRepository->findAll();

        foreach ($mailboxEmails as $mailboxEmail) {
            $senderEmail = $mailboxEmail->getReplyTo();
            if (!$senderEmail) {
                $senderEmail = $mailboxEmail->getFrom();
            }

            $requester = $this->userRepository->findOneBy([
                'email' => $senderEmail,
            ]);

            if (!$requester) {
                $this->markError($mailboxEmail, 'unknown sender');
                continue;
            }

            $requesterOrganization = $requester->getOrganization();

            if (!$requesterOrganization) {
                $this->markError($mailboxEmail, 'sender is not attached to an organization');
                continue;
            }

            $token = new UserToken($requester);

            $ticket = $this->getAnsweredTicket($mailboxEmail, $token);

            if ($ticket) {
                $canAnswerTicket = $this->accessDecisionManager->decide(
                    $token,
                    ['orga:create:tickets:messages'],
                    $ticket->getOrganization(),
                );

                if (!$canAnswerTicket || $ticket->getStatus() === 'closed') {
                    $ticket = null;
                }
            }

            if (!$ticket) {
                $canCreateTicket = $this->accessDecisionManager->decide(
                    $token,
                    ['orga:create:tickets'],
                    $requesterOrganization,
                );

                if (!$canCreateTicket) {
                    $this->markError($mailboxEmail, 'sender has not permission to create tickets');
                    continue;
                }

                $subject = $mailboxEmail->getSubject();

                $ticket = new Ticket();
                $ticket->setCreatedBy($requester);
                $ticket->setTitle($subject);
                $ticket->setType(Ticket::DEFAULT_TYPE);
                $ticket->setStatus(Ticket::DEFAULT_STATUS);
                $ticket->setUrgency(Ticket::DEFAULT_WEIGHT);
                $ticket->setImpact(Ticket::DEFAULT_WEIGHT);
                $ticket->setPriority(Ticket::DEFAULT_WEIGHT);
                $ticket->setOrganization($requesterOrganization);
                $ticket->setRequester($requester);

                $contracts = $this->contractRepository->findOngoingByOrganization($requesterOrganization);
                if (count($contracts) === 1) {
                    $ticket->addContract($contracts[0]);
                }
            }

            $messageContent = $this->appMessageSanitizer->sanitize($mailboxEmail->getBody());

            $message = new Message();
            $message->setCreatedBy($requester);
            $message->setContent($messageContent);
            $message->setTicket($ticket);
            $message->setIsConfidential(false);
            $message->setVia('email');

            $this->ticketRepository->save($ticket, true);
            $this->messageRepository->save($message, true);

            $messageDocuments = $this->storeAttachments($mailboxEmail);

            foreach ($messageDocuments as $messageDocument) {
                $messageDocument->setMessage($message);
                $messageDocument->setCreatedBy($requester);
            }

            $this->messageDocumentRepository->saveBatch($messageDocuments, true);

            $this->mailboxEmailRepository->remove($mailboxEmail, true);

            $this->bus->dispatch(new SendMessageEmail($message->getId()));
        }
    }

    private function getAnsweredTicket(MailboxEmail $mailboxEmail, UserToken $token): ?Ticket
    {
        $replyId = $mailboxEmail->getInReplyTo();

        if ($replyId) {
            $replyMessage = $this->messageRepository->findOneBy([
                'emailId' => $replyId,
            ]);
        } else {
            $replyMessage = null;
        }

        if ($replyMessage) {
            return $replyMessage->getTicket();
        }

        $ticketId = $mailboxEmail->extractTicketId();

        if ($ticketId) {
            return $this->ticketRepository->find($ticketId);
        }

        return null;
    }

    /**
     * @return array<string, MessageDocument>
     */
    private function storeAttachments(MailboxEmail $mailboxEmail): array
    {
        $messageDocuments = [];

        $tmpPath = sys_get_temp_dir();

        foreach ($mailboxEmail->getAttachments() as $attachment) {
            $id = $attachment->getId();
            $filename = $attachment->getName();
            $status = $attachment->save($tmpPath, $filename);

            if (!$status) {
                $this->logger->warning(
                    "MailboxEmail {$mailboxEmail->getId()} cannot import {$filename}: can't save in temporary dir"
                );
                continue;
            }

            $filepath = $tmpPath . '/' . $filename;
            $file = new File($filepath, false);

            try {
                $messageDocuments[$id] = $this->messageDocumentStorage->store($file, $filename);
            } catch (MessageDocumentStorageError $e) {
                $this->logger->warning(
                    "MailboxEmail {$mailboxEmail->getId()} cannot import {$filename}: {$e->getMessage()}"
                );
                continue;
            }
        }

        return $messageDocuments;
    }

    private function markError(MailboxEmail $mailboxEmail, string $error): void
    {
        $this->logger->warning("MailboxEmail {$mailboxEmail->getId()} cannot be imported: {$error}");

        $mailboxEmail->setLastError($error);
        $this->mailboxEmailRepository->save($mailboxEmail, true);
    }
}
