<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Mailbox;

class MailboxSorter extends LocaleSorter
{
    /**
     * @param Mailbox[] $mailboxes
     */
    public function sort(array &$mailboxes): void
    {
        $collator = new \Collator($this->getLocale());
        uasort($mailboxes, function (Mailbox $m1, Mailbox $m2) use ($collator) {
            $nameComparison = $collator->compare($m1->getName(), $m2->getName());
            if ($nameComparison === false) {
                $nameComparison = 0;
            }
            return $nameComparison;
        });
    }
}
