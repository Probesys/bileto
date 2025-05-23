<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\ActivityMonitor\ActiveUser;
use App\Entity\MailboxEmail;
use App\Entity\Message;
use App\Entity\MessageDocument;
use App\Entity\Ticket;
use App\Entity\User;
use App\Message\CreateTicketsFromMailboxEmails;
use App\Repository\MailboxRepository;
use App\Repository\MailboxEmailRepository;
use App\Repository\MessageRepository;
use App\Repository\MessageDocumentRepository;
use App\Repository\OrganizationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Security\Authorizer;
use App\Security\Encryptor;
use App\Service\MessageDocumentStorage;
use App\Service\MessageDocumentStorageError;
use App\Service\TicketAssigner;
use App\Service\UserCreator;
use App\Service\UserCreatorException;
use App\Service\UserService;
use App\TicketActivity\MessageEvent;
use App\TicketActivity\TicketEvent;
use App\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webklex\PHPIMAP;

#[AsMessageHandler]
class CreateTicketsFromMailboxEmailsHandler
{
    public function __construct(
        private MailboxEmailRepository $mailboxEmailRepository,
        private MessageRepository $messageRepository,
        private MessageDocumentRepository $messageDocumentRepository,
        private MessageDocumentStorage $messageDocumentStorage,
        private OrganizationRepository $organizationRepository,
        private TicketRepository $ticketRepository,
        private TicketAssigner $ticketAssigner,
        private UserRepository $userRepository,
        private UserCreator $userCreator,
        private UserService $userService,
        private Authorizer $authorizer,
        private HtmlSanitizerInterface $appMessageSanitizer,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
        private ActiveUser $activeUser,
        private EventDispatcherInterface $eventDispatcher,
        private LockFactory $lockFactory,
    ) {
    }

    public function __invoke(CreateTicketsFromMailboxEmails $message): void
    {
        $mailboxEmails = $this->mailboxEmailRepository->findAll();

        foreach ($mailboxEmails as $mailboxEmail) {
            $lock = $this->lockFactory->createLock("process-mailbox-email.{$mailboxEmail->getId()}", ttl: 1 * 60);

            if (!$lock->acquire()) {
                continue;
            }

            try {
                $this->processMailboxEmail($mailboxEmail);
            } catch (\Exception $e) {
                $error = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                $mailboxEmail->setLastError($error);
                $this->mailboxEmailRepository->save($mailboxEmail, true);

                $this->logger->error("MailboxEmail #{$mailboxEmail->getId()} error: {$e->getMessage()}");
            } finally {
                $lock->release();
            }
        }
    }

    private function processMailboxEmail(MailboxEmail $mailboxEmail): void
    {
        if ($mailboxEmail->isAutoreply()) {
            $this->logger->notice("MailboxEmail #{$mailboxEmail->getId()} ignored: detected as auto reply");

            $this->mailboxEmailRepository->remove($mailboxEmail, true);

            return;
        }

        $senderEmail = $mailboxEmail->getFrom();

        $domain = Utils\Email::extractDomain($senderEmail);
        $domainOrganization = $this->organizationRepository->findOneByDomainOrDefault($domain);

        $requester = $this->userRepository->findOneBy([
            'email' => $senderEmail,
        ]);

        if (!$requester && $domainOrganization) {
            try {
                $requester = $this->userCreator->create(email: $senderEmail);
            } catch (UserCreatorException $e) {
                $errors = Utils\ConstraintErrorsFormatter::format($e->getErrors());
                $errors = implode(' ', $errors);
                $this->markError($mailboxEmail, 'cannot create sender: ' . $errors);
                return;
            }
        } elseif (!$requester) {
            $this->markError($mailboxEmail, 'unknown sender');
            return;
        }

        $requesterOrganization = $this->userService->getDefaultOrganization($requester);

        if (!$requesterOrganization) {
            $this->markError($mailboxEmail, 'sender has not permission to create tickets');
            $this->activeUser->change(null);
            return;
        }

        $this->activeUser->change($requester);

        $ticket = $this->getAnsweredTicket($mailboxEmail);

        if ($ticket) {
            $canAnswerTicket = $this->authorizer->isGrantedToUser(
                $requester,
                'orga:create:tickets:messages',
                $ticket,
            );

            if (!$canAnswerTicket || $ticket->getStatus() === 'closed') {
                $ticket = null;
            }
        }

        $isNewTicket = false;

        if (!$ticket) {
            $subject = $mailboxEmail->getSubject();

            $ticket = new Ticket();
            $ticket->setTitle($subject);
            $ticket->setOrganization($requesterOrganization);
            $ticket->setRequester($requester);

            $responsibleTeam = $this->ticketAssigner->getDefaultResponsibleTeam($requesterOrganization);
            $ticket->setTeam($responsibleTeam);

            $this->ticketRepository->save($ticket, true);
            $isNewTicket = true;
        }

        $messageDocuments = $this->storeAttachments($mailboxEmail);
        $this->messageDocumentRepository->save($messageDocuments, true);

        $messageContent = $mailboxEmail->getBody();
        // Inline attachments (i.e. <img>) have URLs of type: `cid:<id>`.
        // Here we replace these URLs with the application URLs to the message documents.
        $messageContent = $this->replaceAttachmentsUrls($messageContent, $messageDocuments);
        // Sanitize the HTML only after replacing the URLs or it would
        // remove the `src` attributes as the `cid:` scheme is forbidden.
        $messageContent = $this->appMessageSanitizer->sanitize($messageContent);

        $message = new Message();
        $message->setCreatedAt($mailboxEmail->getDate());
        $message->setContent($messageContent);
        $message->setTicket($ticket);
        $message->setIsConfidential(false);
        $message->setVia('email');

        $emailId = $mailboxEmail->getMessageId();
        $message->addEmailNotificationReference($emailId);

        $this->messageRepository->save($message, true);

        if ($isNewTicket) {
            $ticketEvent = new TicketEvent($ticket);
            $this->eventDispatcher->dispatch($ticketEvent, TicketEvent::CREATED);
        }

        $messageEvent = new MessageEvent($message);
        $this->eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED);

        if (!$isNewTicket) {
            $this->eventDispatcher->dispatch($messageEvent, MessageEvent::CREATED_ANSWER);
        }

        $this->mailboxEmailRepository->remove($mailboxEmail, true);

        $this->activeUser->change(null);
    }

    private function getAnsweredTicket(MailboxEmail $mailboxEmail): ?Ticket
    {
        $replyId = $mailboxEmail->getInReplyTo();

        if ($replyId !== null) {
            // Verify if the email answers to a message stored in the database.
            // We handle the case where the email answers to a GLPI email
            // (after a migration from GLPI to Bileto for instance).

            $glpiPattern = '/'
                . 'GLPI'
                . '_(?<uuid>[a-z0-9]+)'
                . '-(?<itemType>[a-z]+)-(?<itemId>[0-9]+)' // this part is optional in GLPI, but required in our case
                . '\/([a-z_]+)' // we don't care about the event, but we need to match it
                . '(\.[0-9]+\.[0-9]+)?' // optional time and random values
                . '@(?<hostname>.+)'
                . '/i';

            $result = preg_match($glpiPattern, $replyId, $matches);

            if ($result === 1) {
                // If the email comes from GLPI, we need to rebuild the
                // reference to remove random parts of the header.
                $reference = "glpi:GLPI_{$matches['uuid']}-{$matches['itemType']}-{$matches['itemId']}";
                $reference .= "@{$matches['hostname']}";
            } else {
                $reference = "email:{$replyId}";
            }

            $replyMessage = $this->messageRepository->findOneByNotificationReference($reference);

            if ($replyMessage) {
                return $replyMessage->getTicket();
            }
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
        $mapping = [];

        foreach ($messageDocuments as $attachmentId => $messageDocument) {
            $initialUrl = 'cid:' . $attachmentId;

            $messageDocumentUrl = $this->urlGenerator->generate(
                'message document',
                [
                    'uid' => $messageDocument->getUid(),
                    'extension' => $messageDocument->getExtension(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $mapping[$initialUrl] = $messageDocumentUrl;
        }

        return Utils\DomHelper::replaceImagesUrls($content, $mapping);
    }

    private function markError(MailboxEmail $mailboxEmail, string $error): void
    {
        $this->logger->warning("MailboxEmail {$mailboxEmail->getId()} cannot be imported: {$error}");

        $mailboxEmail->setLastError($error);
        $this->mailboxEmailRepository->save($mailboxEmail, true);
    }
}
