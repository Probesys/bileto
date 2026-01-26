<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Entity;
use App\Repository;
use App\Message;
use App\Security;
use App\Service;
use App\Utils;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class SendMessageEmailHandler
{
    use EmailSender;

    public function __construct(
        private Repository\MessageRepository $messageRepository,
        private Service\MessageDocumentStorage $messageDocumentStorage,
        private Security\Authorizer $authorizer,
        private TranslatorInterface $translator,
        private TransportInterface $transportInterface,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'MAILER_FROM')]
        private string $mailerFrom,
    ) {
    }

    public function __invoke(Message\SendMessageEmail $data): void
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
        $observers = $ticket->getObservers();
        $assignee = $ticket->getAssignee();

        $locale = $author->getLocale();

        $recipients = [];

        if ($requester !== $author) {
            $recipients[$requester->getEmail()] = $requester;
        }

        foreach ($observers as $observer) {
            if ($observer !== $author) {
                $recipients[$observer->getEmail()] = $observer;
            }
        }

        if ($assignee && $assignee !== $author) {
            $recipients[$assignee->getEmail()] = $assignee;
        }

        $recipients = array_filter($recipients, function (?Entity\User $user): bool {
            // Remove the anonymous users from the list of recipients to avoid
            // to send emails to wrong addresses.
            return $user && !$user->isAnonymized();
        });

        if (empty($recipients)) {
            return;
        }

        $previousMessage = $ticket->getMessageBefore($message, confidential: false);

        $subject = "[#{$ticket->getId()}]";

        if ($ticket->isFinished()) {
            $status = $this->translator->trans($ticket->getStatusLabel(), locale: $locale);
            $subject = "{$subject} [{$status}]";
        }

        $subject = "{$subject} {$ticket->getTitle()}";

        if ($previousMessage) {
            $subject = "Re: {$subject}";
        }

        $email = new TemplatedEmail();
        $from = new Address($this->mailerFrom, $author->getDisplayName());
        $email->from($from);
        $email->subject($subject);
        $email->locale($locale);

        $replacingMapping = [];

        // Attach the message documents as attachments to the email
        foreach ($message->getMessageDocuments() as $messageDocument) {
            $filepath = $this->messageDocumentStorage->getPathname($messageDocument);
            $file = new File($filepath);
            $dataPart = new DataPart(
                $file,
                $messageDocument->getName(),
                $messageDocument->getMimetype()
            );
            $email->addPart($dataPart);

            $initialUrl = $this->urlGenerator->generate(
                'message document',
                [
                    'uid' => $messageDocument->getUid(),
                    'extension' => $messageDocument->getExtension(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $replacingMapping[$initialUrl] = 'cid:' . $dataPart->getContentId();
        }

        // Change the images URLs in the email in order to point to the "cid" references.
        $content = $message->getContent();
        $content = Utils\DomHelper::replaceImagesUrls($content, $replacingMapping);

        $email->htmlTemplate('emails/message.html.twig');
        $email->textTemplate('emails/message.txt.twig');

        // Set correct references headers so email clients can add the email to
        // the conversation thread.
        $emailReferences = [];
        foreach ($ticket->getMessages(confidential: false) as $message) {
            $references = $message->getEmailNotificationsReferences();
            if ($references) {
                $emailReferences = array_merge($emailReferences, $references);
            }
        }

        if ($emailReferences) {
            $email->getHeaders()->addIdHeader('References', $emailReferences);
        }

        if ($previousMessage) {
            $references = $previousMessage->getEmailNotificationsReferences();

            if ($references) {
                $email->getHeaders()->addIdHeader('In-Reply-To', $references[0]);
            }
        }

        // Ask compliant autoresponders to not reply to this email
        $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');

        foreach ($recipients as $user) {
            $userCanSeeTicket = $this->authorizer->isGrantedForUser($user, 'orga:see', $ticket);

            $email->context([
                'subject' => $subject,
                'ticket' => $ticket,
                'content' => $content,
                'linkToBileto' => $user->canLogin() && $userCanSeeTicket,
            ]);

            $email->to($user->getEmailAddress());

            $emailId = $this->sendEmail($email);

            $message->addEmailNotificationReference($emailId);
        }

        try {
            $this->messageRepository->save($message, true);
        } catch (EntityNotFoundException $e) {
            $this->logger->error(
                "Message #{$message->getId()} notification reference cannot be saved: {$e->getMessage()}"
            );
        }
    }
}
