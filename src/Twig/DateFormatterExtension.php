<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Service\DateTranslator;
use App\Utils\Time;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateFormatterExtension extends AbstractExtension
{
    private DateTranslator $dateTranslator;

    public function __construct(DateTranslator $dateTranslator)
    {
        $this->dateTranslator = $dateTranslator;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('dateTrans', [$this, 'dateTrans']),
            new TwigFilter('dateIso', [$this, 'dateIso']),
            new TwigFilter('dateFull', [$this, 'dateFull']),
            new TwigFilter('dateShort', [$this, 'dateShort']),
        ];
    }

    public function dateTrans(\DateTimeInterface $date, string $format = 'dd MMM Y, HH:mm'): string
    {
        return $this->dateTranslator->format($date, $format);
    }

    public function dateIso(\DateTimeInterface $date): string
    {
        return $date->format(\DateTimeInterface::ATOM);
    }

    public function dateFull(\DateTimeInterface $date, bool $fullMonth = false, bool $cleverYear = true): string
    {
        $today = Time::relative('today');
        $currentYear = $today->format('Y');
        $dateYear = $date->format('Y');

        $format = 'dd';

        if ($fullMonth) {
            $format .= ' MMMM';
        } else {
            $format .= ' MMM';
        }

        if (!$cleverYear || $currentYear !== $dateYear) {
            $format .= ' Y';
        }

        $format .= ', HH:mm';

        return $this->dateTrans($date, $format);
    }

    public function dateShort(\DateTimeInterface $date, bool $fullMonth = false, bool $cleverYear = true): string
    {
        $today = Time::relative('today');
        $currentYear = $today->format('Y');
        $dateYear = $date->format('Y');

        $format = 'dd';

        if ($fullMonth) {
            $format .= ' MMMM';
        } else {
            $format .= ' MMM';
        }

        if (!$cleverYear || $currentYear !== $dateYear) {
            $format .= ' Y';
        }

        return $this->dateTrans($date, $format);
    }
}
