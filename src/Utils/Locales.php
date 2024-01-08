<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

class Locales
{
    public const DEFAULT_LOCALE = 'en_GB';

    public const SUPPORTED_LOCALES = ['en_GB', 'fr_FR'];

    /**
     * @return array<string, string>
     */
    public static function getSupportedLanguages(): array
    {
        return [
            'en_GB' => 'English',
            'fr_FR' => 'Français',
        ];
    }

    public static function isAvailable(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES);
    }

    /**
     * @param string[] $requestedLocales
     */
    public static function getBest(array $requestedLocales): string
    {
        // First, look for an exact match in the supported locales:
        foreach ($requestedLocales as $locale) {
            if (self::isAvailable($locale)) {
                return $locale;
            }
        }

        // Then, create an array to match "super" locales (e.g. en) to
        // supported country locales (e.g. en_GB):
        $supersToLocales = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $splitLocale = explode('_', $locale, 2);
            if (count($splitLocale) < 2) {
                continue;
            }

            $superLocale = $splitLocale[0];
            if (!isset($supersToLocales[$superLocale])) {
                $supersToLocales[$superLocale] = $locale;
            }
        }

        // And search requested locales in the lookup array:
        foreach ($requestedLocales as $locale) {
            $splitLocale = explode('_', $locale, 2);
            if (count($splitLocale) === 1) {
                $superLocale = $locale;
            } else {
                $superLocale = $splitLocale[0];
            }

            if (isset($supersToLocales[$superLocale])) {
                return $supersToLocales[$superLocale];
            }
        }

        // Bileto doesn't support the requested locales, let's return the
        // default one.
        return self::DEFAULT_LOCALE;
    }
}
