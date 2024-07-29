<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Entity\Label;

class LabelSorter extends LocaleSorter
{
    /**
     * @param Label[] $labels
     */
    public function sort(array &$labels): void
    {
        uasort($labels, function (Label $l1, Label $l2): int {
            return $this->localeCompare($l1->getName(), $l2->getName());
        });
    }
}
