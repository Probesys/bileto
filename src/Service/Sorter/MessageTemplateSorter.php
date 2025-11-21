<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
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
        uasort($messageTemplates, function (MessageTemplate $template1, MessageTemplate $template2): int {
            $type1 = $template1->getType();
            $type2 = $template2->getType();

            if ($type1 === $type2) {
                return $this->localeCompare($template1->getName(), $template2->getName());
            }

            $typeOrder1 = array_search($type1, MessageTemplate::TYPES);
            $typeOrder2 = array_search($type2, MessageTemplate::TYPES);
            return $typeOrder1 <=> $typeOrder2;
        });
    }
}
