<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\ActivityMonitor\ActiveUser;
use App\Entity\MailboxEmail;
use App\Entity\Message;
use App\Entity\MessageDocument;
use App\Entity\Ticket;
use App\Message\CreateTicketsFromMailboxEmails;
use App\Message\SendMessageEmail;
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
use App\TicketActivity\MessageEvent;
use App\TicketActivity\TicketEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Webklex\PHPIMAP;

#[AsMessageHandler]
class CreateTicketsFromMailboxEmailsHandler
{
    public function __construct(
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
        private UrlGeneratorInterface $urlGenerator,
        private ActiveUser $activeUser,
        private EventDispatcherInterface $eventDispatcher,
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

            $this->activeUser->change($requester);

            $token = new UserToken($requester);

            $ticket = $this->getAnsweredTicket($mailboxEmail);

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

            $isNewTicket = false;

            if (!$ticket) {
                $canCreateTicket = $this->accessDecisionManager->decide(
                    $token,
                    ['orga:create:tickets'],
                    $requesterOrganization,
                );

                if (!$canCreateTicket) {
                    $this->markError($mailboxEmail, 'sender has not permission to create tickets');
                    $this->activeUser->change(null);
                    continue;
                }

                $subject = $mailboxEmail->getSubject();

                $ticket = new Ticket();
                $ticket->setTitle($subject);
                $ticket->setOrganization($requesterOrganization);
                $ticket->setRequester($requester);

                $this->ticketRepository->save($ticket, true);
                $isNewTicket = true;
            }

            $messageDocuments = $this->storeAttachments($mailboxEmail);
            $this->messageDocumentRepository->saveBatch($messageDocuments, true);

            $messageContent = $mailboxEmail->getBody();
            // Inline attachments (i.e. <img>) have URLs of type: `cid:<id>`.
            // Here we replace these URLs with the application URLs to the message documents.
            $messageContent = $this->replaceAttachmentsUrls($messageContent, $messageDocuments);
            // Sanitize the HTML only after replacing the URLs or it would
            // remove the `src` attributes as the `cid:` scheme is forbidden.
            $messageContent = $this->appMessageSanitizer->sanitize($messageContent);

            $message = new Message();
            $message->setContent($messageContent);
            $message->setTicket($ticket);
            $message->setIsConfidential(false);
            $message->setVia('email');

            $this->messageRepository->save($message, true);

            if ($isNewTicket) {
                $ticketEvent = new TicketEvent($ticket);
                $this->eventDispatcher->dispatch($ticketEvent, TicketEvent::CREATED);
            }

            $messageEvent = new MessageEvent($message);
            $this->eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED);

            $this->mailboxEmailRepository->remove($mailboxEmail, true);

            $this->bus->dispatch(new SendMessageEmail($message->getId()));

            $this->activeUser->change(null);
        }
    }

    private function getAnsweredTicket(MailboxEmail $mailboxEmail): ?Ticket
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
            // PHP-IMAP can return invalid UTF-8 characters in some circumstances.
            // mb_convert_encoding will replace these characters with the
            // character "?".
            // Bug issue: https://github.com/Webklex/php-imap/issues/459
            $filename = mb_convert_encoding($filename, 'UTF-8', 'UTF-8');
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

    /**
     * Replace (in the given content) the image src attributes of the inline
     * attachments with the URLs of the corresponding message documents.
     *
     * @param array<string, MessageDocument> $messageDocuments
     */
    private function replaceAttachmentsUrls(string $content, array $messageDocuments): string
    {
        if (!$content) {
            return '';
        }

        $contentDom = new \DOMDocument();

        // DOMDocument::loadHTML considers the source string to be encoded in
        // ISO-8859-1 by default. In order to not ending with weird characters,
        // we encode the non-ASCII chars (i.e. all chars above >0x80) to HTML
        // entities.
        $content = mb_encode_numericentity(
            $content,
            [0x80, 0x10FFFF, 0, -1],
            'UTF-8'
        );

        $contentDom->loadHTML($content);
        $contentDomXPath = new \DomXPath($contentDom);

        foreach ($messageDocuments as $attachmentId => $messageDocument) {
            $imageNodes = $contentDomXPath->query("//img[@src='cid:{$attachmentId}']");

            if ($imageNodes === false || $imageNodes->length === 0) {
                // no corresponding node, the document was probably not inlined
                continue;
            }

            $messageDocumentUrl = $this->urlGenerator->generate(
                'message document',
                [
                    'uid' => $messageDocument->getUid(),
                    'extension' => $messageDocument->getExtension(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            foreach ($imageNodes as $imageNode) {
                if ($imageNode instanceof \DOMElement) {
                    $imageNode->setAttribute('src', $messageDocumentUrl);
                }
            }
        }

        $result = $contentDom->saveHTML();

        if ($result === false) {
            return $content;
        }

        return $result;
    }

    private function markError(MailboxEmail $mailboxEmail, string $error): void
    {
        $this->logger->warning("MailboxEmail {$mailboxEmail->getId()} cannot be imported: {$error}");

        $mailboxEmail->setLastError($error);
        $this->mailboxEmailRepository->save($mailboxEmail, true);
    }
}
