<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class DateTranslator
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function format(\DateTimeInterface $date, string $format = 'dd MMM Y, HH:mm'): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $currentLocale = $request->getLocale();

        $formatter = new \IntlDateFormatter(
            $currentLocale,
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
            null,
            null,
            $format
        );

        $translatedDate = $formatter->format($date);
        if ($translatedDate === false) {
            throw new \Exception("Cannot translate the date (format: {$format})");
        }

        return $translatedDate;
    }
}
