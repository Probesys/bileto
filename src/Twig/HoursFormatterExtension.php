<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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
     *
     * @param 'short'|'long' $format
     */
    public function formatHours(int|float $hours, string $format = 'short'): string
    {
        $hoursOnly = intval(floor($hours));
        $minutes = intval(($hours - $hoursOnly) * 60);

        return $this->format($hoursOnly, $minutes, $format);
    }

    /**
     * Return the given minutes formatted as hours and minutes.
     *
     * @param 'short'|'long' $format
     */
    public function formatMinutes(int $minutes, string $format = 'short'): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $this->format($hours, $remainingMinutes, $format);
    }

    /**
     * @param 'short'|'long' $format
     */
    private function format(int $hours, int $minutes, string $format = 'short'): string
    {
        if ($format === 'long') {
            if ($minutes === 0) {
                return $this->translator->trans('hours_formatter.hours.long', [
                    'hours' => $hours,
                ]);
            } elseif ($hours === 0) {
                return $this->translator->trans('hours_formatter.minutes.long', [
                    'minutes' => $minutes,
                ]);
            } else {
                return $this->translator->trans('hours_formatter.hours_and_minutes.long', [
                    'hours' => $hours,
                    'minutes' => $minutes,
                ]);
            }
        } else {
            if ($minutes === 0) {
                return $this->translator->trans('hours_formatter.hours.short', [
                    'hours' => $hours,
                ]);
            } elseif ($hours === 0) {
                return $this->translator->trans('hours_formatter.minutes.short', [
                    'minutes' => $minutes,
                ]);
            } else {
                return $this->translator->trans('hours_formatter.hours_and_minutes.short', [
                    'hours' => $hours,
                    'minutes' => $minutes,
                ]);
            }
        }
    }
}
