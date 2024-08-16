<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Locales
{
    public const SUPPORTED_LOCALES = [
        'en_GB' => 'English',
        'fr_FR' => 'Français',
    ];

    /** @var key-of<self::SUPPORTED_LOCALES> */
    private string $defaultLocale;

    /**
     * @return array<key-of<self::SUPPORTED_LOCALES>>
     */
    public static function getSupportedLocalesCodes(): array
    {
        return array_keys(self::SUPPORTED_LOCALES);
    }

    public function __construct(
        #[Autowire(env: 'default::APP_DEFAULT_LOCALE')]
        ?string $defaultLocale,
    ) {
        if ($defaultLocale && $this->isAvailable($defaultLocale)) {
            /** @var key-of<self::SUPPORTED_LOCALES> */
            $defaultLocale = $defaultLocale;
            $this->defaultLocale = $defaultLocale;
        } else {
            $this->defaultLocale = 'en_GB';
        }
    }

    /**
     * @return key-of<self::SUPPORTED_LOCALES>
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function isAvailable(string $locale): bool
    {
        return isset(self::SUPPORTED_LOCALES[$locale]);
    }

    /**
     * @param string[] $requestedLocales
     */
    public function getBest(array $requestedLocales): string
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
        foreach (self::SUPPORTED_LOCALES as $locale => $localeName) {
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
        return self::getDefaultLocale();
    }
}
