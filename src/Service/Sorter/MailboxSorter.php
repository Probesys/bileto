<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Mailbox;

class MailboxSorter extends LocaleSorter
{
    /**
     * @param Mailbox[] $mailboxes
     */
    public function sort(array &$mailboxes): void
    {
        uasort($mailboxes, function (Mailbox $m1, Mailbox $m2): int {
            return $this->localeCompare($m1->getName(), $m2->getName());
        });
    }
}
