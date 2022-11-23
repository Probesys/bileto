<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Service\DateTranslator;
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
}
