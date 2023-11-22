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
            new TwigFilter('formatHours', [$this, 'formatHours']),
            new TwigFilter('formatMinutes', [$this, 'formatMinutes']),
        ];
    }

    /**
     * Return the given hours formatted as hours and minutes.
     *
     * Hours can be given as float. The decimal part of the number is
     * transformed to minutes. For instance, 2.5 will return "2h 30m".
     */
    public function formatHours(int|float $hours): string
    {
        $hoursOnly = intval(floor($hours));
        $minutes = intval(($hours - $hoursOnly) * 60);

        return $this->format($hoursOnly, $minutes);
    }

    /**
     * Return the given minutes formatted as hours and minutes.
     */
    public function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $this->format($hours, $remainingMinutes);
    }

    private function format(int $hours, int $minutes): string
    {
        if ($minutes === 0) {
            return $this->translator->trans('hours_formatter.hours', [
                'hours' => $hours,
            ]);
        } elseif ($hours === 0) {
            return $this->translator->trans('hours_formatter.minutes', [
                'minutes' => $minutes,
            ]);
        } else {
            return $this->translator->trans('hours_formatter.hours_and_minutes', [
                'hours' => $hours,
                'minutes' => $minutes,
            ]);
        }
    }
}
