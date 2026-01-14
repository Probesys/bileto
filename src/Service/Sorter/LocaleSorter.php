<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Service;
use Symfony\Component\HttpFoundation\RequestStack;

class LocaleSorter
{
    private ?\Collator $collator = null;

    public function __construct(
        private RequestStack $requestStack,
        private Service\Locales $locales,
    ) {
    }

    protected function getCollator(): \Collator
    {
        if (!$this->collator) {
            $currentRequest = $this->requestStack->getCurrentRequest();

            if ($currentRequest) {
                $locale = $currentRequest->getLocale();
            } else {
                $locale = $this->locales->getDefaultLocale();
            }

            $this->collator = new \Collator($locale);
        }

        return $this->collator;
    }

    public function localeCompare(string $field1, string $field2): int
    {
        $collator = $this->getCollator();

        $fieldsComparison = $collator->compare($field1, $field2);

        if ($fieldsComparison === false) {
            $fieldsComparison = strcmp($field1, $field2);
        }

        return $fieldsComparison;
    }
}
