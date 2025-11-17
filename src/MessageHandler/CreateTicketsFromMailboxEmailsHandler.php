<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\ActivityMonitor;
use App\Entity;
use App\Message;
use App\Repository;
use App\Security;
use App\Service;
use App\TicketActivity;
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
        private Repository\MailboxEmailRepository $mailboxEmailRepository,
        private Repository\MessageRepository $messageRepository,
        private Repository\MessageDocumentRepository $messageDocumentRepository,
        private Repository\OrganizationRepository $organizationRepository,
        private Repository\TicketRepository $ticketRepository,
        private Repository\UserRepository $userRepository,
        private Service\MessageDocumentStorage $messageDocumentStorage,
        private Service\TicketAssigner $ticketAssigner,
        private Service\UserCreator $userCreator,
        private Service\UserService $userService,
        private Security\Authorizer $authorizer,
        private ActivityMonitor\ActiveUser $activeUser,
        private HtmlSanitizerInterface $appMessageSanitizer,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
        private EventDispatcherInterface $eventDispatcher,
        private LockFactory $lockFactory,
    ) {
    }

    public function __invoke(Message\CreateTicketsFromMailboxEmails $message): void
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

    /**
     * Create a message (and eventually a ticket) corresponding to the given
     * mailbox email.
     */
    private function processMailboxEmail(Entity\MailboxEmail $mailboxEmail): void
    {
        if ($mailboxEmail->isAutoreply()) {
            $this->logger->notice("MailboxEmail #{$mailboxEmail->getId()} ignored: detected as auto reply");

            $this->mailboxEmailRepository->remove($mailboxEmail, true);

            return;
        }

        // First, get the user matching with the sender of the email.
        $sender = $this->getSender($mailboxEmail);

        if (!$sender) {
            return;
        }

        // Set the active user so the created entities (e.g. ticket, message)
        // will have the sender as `createdAt`.
        $this->activeUser->change($sender);

        // Then, get the ticket corresponding to the email. If the email
        // doesn't answer to a ticket, a new one will be returned if possible.
        $ticket = $this->getTicket($mailboxEmail, $sender);

        if (!$ticket) {
            $this->activeUser->change(null);
            return;
        }

        $isNewTicket = $ticket->getId() === null;
        if ($isNewTicket) {
            $this->ticketRepository->save($ticket, flush: true);
        }

        // The important part: create the message by using the email
        // information and attach it to the ticket.
        $message = $this->createMessage($mailboxEmail, $ticket);

        // Finally, dispatch the different events corresponding to what happened.
        if ($isNewTicket) {
            $ticketEvent = new TicketActivity\TicketEvent($ticket);
            $this->eventDispatcher->dispatch($ticketEvent, TicketActivity\TicketEvent::CREATED);
        }

        $messageEvent = new TicketActivity\MessageEvent($message);
        $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED);

        if (!$isNewTicket) {
            $this->eventDispatcher->dispatch($messageEvent, TicketActivity\MessageEvent::CREATED_ANSWER);
        }

        $this->mailboxEmailRepository->remove($mailboxEmail, true);

        $this->activeUser->change(null);
    }

    /**
     * Return the user corresponding to the email sender address.
     *
     * If the user is unknown, but the email domain is handled by an
     * organization, a new user is created and returned.
     *
     * Otherwise, the email is marked with an error and null is returned.
     */
    private function getSender(Entity\MailboxEmail $mailboxEmail): ?Entity\User
    {
        $senderEmail = $mailboxEmail->getFrom();

        $sender = $this->userRepository->findOneBy([
            'email' => $senderEmail,
        ]);

        if (!$sender) {
            // We don't know the sender, but maybe there is a default organization
            // matching the domain of his email?
            $domain = Utils\Email::extractDomain($senderEmail);
            $domainOrganization = $this->organizationRepository->findOneByDomainOrDefault($domain);

            if (!$domainOrganization) {
                $this->markError($mailboxEmail, 'unknown sender');
                return null;
            }

            // There is a default organization? Then try to create the user.
            try {
                $sender = $this->userCreator->create(email: $senderEmail);
            } catch (Service\UserCreatorException $e) {
                $errors = Utils\ConstraintErrorsFormatter::format($e->getErrors());
                $errors = implode(' ', $errors);
                $this->markError($mailboxEmail, 'cannot create sender: ' . $errors);
                return null;
            }
        }

        return $sender;
    }

    /**
     * Return the ticket corresponding to the email.
     *
     * It returns a ticket in two cases:
     *
     * - The email answers to an existing ticket and the sender has the
     *   permission to answer to it.
     * - Otherwise, if the sender has a default organization, a new ticket is
     *   built. In this case, the code calling this method is in charge to save
     *   it in the database.
     *
     * In other cases, the email is marked with an error and null is returned.
     */
    private function getTicket(
        Entity\MailboxEmail $mailboxEmail,
        Entity\User $sender,
    ): ?Entity\Ticket {
        $ticket = $this->getAnsweredTicket($mailboxEmail);

        if ($ticket && $this->canAnswerTicket($sender, $ticket)) {
            // The easiest case: the email answers to a ticket and the sender
            // can answer to it.
            return $ticket;
        }

        if ($ticket) {
            $this->logger->notice(
                "MailboxEmail #{$mailboxEmail->getId()}: " .
                "sender {$sender->getEmail()} cannot answer to ticket #{$ticket->getId()}"
            );
            $ticket = null;
        }

        $defaultOrganization = $this->userService->getDefaultOrganization($sender);

        if (!$defaultOrganization) {
            // By definition, the default organization is an organization in
            // which the user can create tickets. If he has none, then he
            // cannot create tickets.
            $this->markError($mailboxEmail, 'sender has not permission to create tickets');
            return null;
        }

        // Finally, build a ticket.
        $subject = $mailboxEmail->getSubject();

        $ticket = new Entity\Ticket();
        $ticket->setTitle($subject);
        $ticket->setOrganization($defaultOrganization);
        $ticket->setRequester($sender);

        $responsibleTeam = $this->ticketAssigner->getDefaultResponsibleTeam($defaultOrganization);
        $ticket->setTeam($responsibleTeam);

        foreach ($defaultOrganization->getObservers() as $observer) {
            $ticket->addObserver($observer);
        }

        return $ticket;
    }

    /**
     * Return a ticket referenced by the email.
     *
     * A ticket is referenced by an email either if:
     *
     * - The `In-Reply-To` header references a previous email sent by Bileto
     *   (i.e. referenced by a Message).
     * - The subject or content is referencing a ticket id.
     */
    private function getAnsweredTicket(Entity\MailboxEmail $mailboxEmail): ?Entity\Ticket
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
     * Return whether the user can answer the ticket.
     *
     * The user must have the orga:create:tickets:messages permission on the
     * ticket's organization, and the ticket must not be closed.
     */
    private function canAnswerTicket(Entity\User $user, Entity\Ticket $ticket): bool
    {
        $canAnswerTicket = $this->authorizer->isGrantedForUser(
            $user,
            'orga:create:tickets:messages',
            $ticket,
        );

        return $canAnswerTicket && !$ticket->isClosed();
    }

    /**
     * Create and return a message by using the email data (e.g. headers, body,
     * attachments) and attach it to the ticket.
     */
    private function createMessage(
        Entity\MailboxEmail $mailboxEmail,
        Entity\Ticket $ticket,
    ): Entity\Message {
        // Extract the attachments and format the email body correctly.
        $messageDocuments = $this->storeAttachments($mailboxEmail);
        $this->messageDocumentRepository->save($messageDocuments, true);

        $messageContent = $mailboxEmail->getBody();
        // Inline attachments (i.e. <img>) have URLs of type: `cid:<id>`.
        // Here we replace these URLs with the application URLs to the message documents.
        $messageContent = $this->replaceAttachmentsUrls($messageContent, $messageDocuments);
        // Sanitize the HTML only after replacing the URLs or it would
        // remove the `src` attributes as the `cid:` scheme is forbidden.
        $messageContent = $this->appMessageSanitizer->sanitize($messageContent);

        // Finally, we create the message corresponding to the email.
        $message = new Entity\Message();
        $message->setPostedAt($mailboxEmail->getDate());
        $message->setContent($messageContent);
        $message->setTicket($ticket);
        $message->setIsConfidential(false);
        $message->setVia('email');

        $emailId = $mailboxEmail->getMessageId();
        $message->addEmailNotificationReference($emailId);

        $this->messageRepository->save($message, true);

        return $message;
    }

    /**
     * Store the email attachments as MessageDocuments.
     *
     * @return array<string, Entity\MessageDocument>
     */
    private function storeAttachments(Entity\MailboxEmail $mailboxEmail): array
    {
        $messageDocuments = [];

        $tmpPath = sys_get_temp_dir();

        foreach ($mailboxEmail->getAttachments() as $attachment) {
            $id = $attachment->getId();
            $originalFilename = $attachment->getName();
            // PHP-IMAP can return invalid UTF-8 characters in some circumstances.
            // mb_convert_encoding will replace these characters with the
            // character "?".
            // Bug issue: https://github.com/Webklex/php-imap/issues/459
            $filename = mb_convert_encoding($originalFilename, 'UTF-8', 'UTF-8');

            $status = $attachment->save($tmpPath, $filename);

            if (!$status) {
                $this->logger->warning(
                    "MailboxEmail #{$mailboxEmail->getId()} cannot import {$filename}: can't save in temporary dir"
                );
                continue;
            }

            $filepath = $tmpPath . '/' . $filename;
            $file = new File($filepath, false);

            try {
                $messageDocuments[$id] = $this->messageDocumentStorage->store($file, $filename);
            } catch (Service\MessageDocumentStorageError $e) {
                $this->logger->warning(
                    "MailboxEmail #{$mailboxEmail->getId()} cannot import {$filename}: {$e->getMessage()}"
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
     * @param array<string, Entity\MessageDocument> $messageDocuments
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

    private function markError(Entity\MailboxEmail $mailboxEmail, string $error): void
    {
        $this->logger->warning("MailboxEmail #{$mailboxEmail->getId()} cannot be imported: {$error}");

        $mailboxEmail->setLastError($error);
        $this->mailboxEmailRepository->save($mailboxEmail, true);
    }
}
