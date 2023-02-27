<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

use App\Entity\EntityEvent;
use App\Entity\Message;

class Timeline
{
    /** @var array<string, array<Message|EntityEvent>> $items */
    private array $items = [];

    /**
     * @param array<Message|EntityEvent> $items
     */
    public function addItems(array $items): void
    {
        foreach ($items as $item) {
            $type = $item->getTimelineType();
            $this->items[$type][] = $item;
        }
    }

    /**
     * @return array<Message|EntityEvent>
     */
    public function getItems(?string $type = null): array
    {
        if ($type) {
            return $this->items[$type] ?? [];
        } else {
            return array_merge(...array_values($this->items));
        }
    }

    /**
     * @return array<Message|EntityEvent>
     */
    public function getSortedItems(?string $type = null): array
    {
        $items = $this->getItems($type);

        uasort($items, function ($i1, $i2) {
            $createdAt1 = $i1->getCreatedAt();
            $createdAt2 = $i2->getCreatedAt();

            if ($createdAt1 < $createdAt2) {
                return -1;
            } elseif ($createdAt1 > $createdAt2) {
                return 1;
            } elseif ($i1->getTimelineType() === 'message') {
                return -1;
            } elseif ($i1->getTimelineType() === 'event') {
                return 1;
            } else {
                return 0;
            }
        });

        return $items;
    }

    public function countItems(?string $type = null): int
    {
        $items = $this->getItems($type);
        return count($items);
    }
}
