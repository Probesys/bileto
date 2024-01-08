<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LocaleToBCP47Extension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'locale_to_bcp47',
                [$this, 'localeToBCP47'],
            ),
        ];
    }

    public function localeToBCP47(string $locale): string
    {
        $splittedLocale = explode('_', $locale, 2);
        if (count($splittedLocale) === 1) {
            return $splittedLocale[0];
        }

        return $splittedLocale[0] . '-' . strtoupper($splittedLocale[1]);
    }
}
