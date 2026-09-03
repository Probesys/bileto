<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Repository;
use Twig\Attribute\AsTwigFunction;

class MailboxesExtension
{
    public function __construct(
        private readonly Repository\MailboxEmailRepository $mailboxEmailRepository,
    ) {
    }

    #[AsTwigFunction('have_mailbox_in_error')]
    public function haveMailboxInError(): bool
    {
        $errorMailboxEmails = $this->mailboxEmailRepository->findInError();
        return count($errorMailboxEmails) > 0;
    }
}
