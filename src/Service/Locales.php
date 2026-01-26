<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
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
        #[Autowire(env: 'APP_DEFAULT_LOCALE')]
        string $defaultLocale,
    ) {
        if ($this->isAvailable($defaultLocale)) {
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
}
