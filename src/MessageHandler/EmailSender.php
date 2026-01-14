<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\MessageHandler;

use Symfony\Component\Mime;

trait EmailSender
{
    /**
     * Send an email and returns its Message-ID.
     *
     * Note that the returned ID is not necessarily the one that will be set on
     * the email received by the recipient (as the SMTP server can alter it for
     * instance). This method does its best to return the correct one though.
     * It seems to work in most of the cases.
     */
    private function sendEmail(Mime\Message $email): string
    {
        // Make sure to generate a valid Message-ID as it will serve later as a
        // fallback.
        $emailHeaders = $email->getHeaders();
        if (!$emailHeaders->has('Message-ID')) {
            $emailHeaders->addIdHeader('Message-ID', $email->generateMessageId());
        }

        $sentMessage = $this->transportInterface->send($email);

        try {
            $sentMessageId = $sentMessage->getMessageId();

            // This can fail if the returned Message-ID is invalid. In our
            // case, the ID was an internal id of our SMTP server which cannot
            // be used usefully.
            new Mime\Address($sentMessageId);

            return $sentMessageId;
        } catch (Mime\Exception\RfcComplianceException $e) {
            // If the Message-ID header of the sent email was invalid, we
            // return the ID of the original email.
            /** @var Mime\Header\IdentificationHeader */
            $messageIdHeader = $emailHeaders->get('Message-ID');
            return $messageIdHeader->getId();
        }
    }
}
