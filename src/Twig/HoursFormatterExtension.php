<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class HoursFormatterExtension extends AbstractExtension
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('formatMinutes', [$this, 'formatMinutes']),
        ];
    }

    /**
     * Return the given minutes formatted as hours and minutes.
     */
    public function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $this->translator->trans('hours_formatter.hours', [
                'hours' => $hours,
            ]);
        } elseif ($hours === 0) {
            return $this->translator->trans('hours_formatter.minutes', [
                'minutes' => $remainingMinutes,
            ]);
        } else {
            return $this->translator->trans('hours_formatter.hours_and_minutes', [
                'hours' => $hours,
                'minutes' => $remainingMinutes,
            ]);
        }
    }
}
