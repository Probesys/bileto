<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\MessageTemplate;

class MessageTemplateSorter extends LocaleSorter
{
    /**
     * @param MessageTemplate[] $messageTemplates
     */
    public function sort(array &$messageTemplates): void
    {
        uasort($messageTemplates, function (MessageTemplate $mt1, MessageTemplate $mt2): int {
            return $this->localeCompare($mt1->getName(), $mt2->getName());
        });
    }
}
