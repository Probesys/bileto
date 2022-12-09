<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
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
}
