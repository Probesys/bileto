<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Service;
use App\Utils;
use Twig\Attribute\AsTwigFilter;

class DateFormatterExtension
{
    public function __construct(
        private Service\DateTranslator $dateTranslator,
    ) {
    }

    #[AsTwigFilter('dateTrans')]
    public function dateTrans(\DateTimeInterface $date, string $format = 'dd MMM yyyy, HH:mm'): string
    {
        return $this->dateTranslator->format($date, $format);
    }

    #[AsTwigFilter('dateIso')]
    public function dateIso(\DateTimeInterface $date): string
    {
        return $date->format(\DateTimeInterface::ATOM);
    }

    #[AsTwigFilter('dateFull')]
    public function dateFull(\DateTimeInterface $date, bool $fullMonth = false, bool $cleverYear = true): string
    {
        $today = Utils\Time::relative('today');
        $currentYear = $today->format('Y');
        $dateYear = $date->format('Y');

        $format = 'dd';

        if ($fullMonth) {
            $format .= ' MMMM';
        } else {
            $format .= ' MMM';
        }

        if (!$cleverYear || $currentYear !== $dateYear) {
            $format .= ' yyyy';
        }

        $format .= ', HH:mm';

        return $this->dateTrans($date, $format);
    }

    #[AsTwigFilter('dateShort')]
    public function dateShort(\DateTimeInterface $date, bool $fullMonth = false, bool $cleverYear = true): string
    {
        $today = Utils\Time::relative('today');
        $currentYear = $today->format('Y');
        $dateYear = $date->format('Y');

        $format = 'dd';

        if ($fullMonth) {
            $format .= ' MMMM';
        } else {
            $format .= ' MMM';
        }

        if (!$cleverYear || $currentYear !== $dateYear) {
            $format .= ' yyyy';
        }

        return $this->dateTrans($date, $format);
    }
}
