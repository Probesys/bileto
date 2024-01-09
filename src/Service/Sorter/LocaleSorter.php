<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service\Sorter;

use App\Utils\Locales;
use Symfony\Component\HttpFoundation\RequestStack;

class LocaleSorter
{
    private RequestStack $requestStack;

    private ?\Collator $collator = null;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    protected function getCollator(): \Collator
    {
        if (!$this->collator) {
            $currentRequest = $this->requestStack->getCurrentRequest();

            if ($currentRequest) {
                $locale = $currentRequest->getLocale();
            } else {
                $locale = Locales::DEFAULT_LOCALE;
            }

            $this->collator = new \Collator($locale);
        }

        return $this->collator;
    }

    public function localeCompare(string $field1, string $field2): int
    {
        $collator = $this->getCollator();

        /** @var int|false */
        $fieldsComparison = $collator->compare($field1, $field2);

        if ($fieldsComparison === false) {
            $fieldsComparison = 0;
        }

        return $fieldsComparison;
    }
}
