<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use App\Repository;
use App\Message;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class SendResetPasswordEmailHandler
{
    public function __construct(
        private Repository\UserRepository $userRepository,
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private TransportInterface $transportInterface,
    ) {
    }

    public function __invoke(Message\SendResetPasswordEmail $data): void
    {
        $userId = $data->getUserId();
        $user = $this->userRepository->find($userId);

        if (!$user) {
            $this->logger->error("User {$userId} cannot be found in SendResetPasswordEmailHandler.");
            return;
        }

        $resetToken = $user->getResetPasswordToken();

        if (!$resetToken || !$resetToken->isValid()) {
            $this->logger->error("User {$userId} resetPasswordToken is invalid in SendResetPasswordEmailHandler.");
            return;
        }

        $locale = $user->getLocale();
        $subject = $this->translator->trans('emails.reset_password.subject', locale: $locale);

        $email = new TemplatedEmail();
        $email->to($user->getEmail());
        $email->subject($subject);
        $email->locale($locale);
        $email->context([
            'subject' => $subject,
            'user' => $user,
            'token' => $resetToken,
        ]);
        $email->htmlTemplate('emails/reset_password.html.twig');
        $email->textTemplate('emails/reset_password.txt.twig');

        // Ask compliant autoresponders to not reply to this email
        $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');

        $this->transportInterface->send($email);
    }
}
