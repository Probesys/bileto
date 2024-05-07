<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Utils;

use App\Utils\Locales;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalesTest extends TestCase
{
    /**
     * @param string[] $requestedLocales
     */
    #[DataProvider('englishRequestedLocale')]
    public function testGetBestWithEnglish(array $requestedLocales): void
    {
        $locale = Locales::getBest($requestedLocales);

        $this->assertSame('en_GB', $locale);
    }

    /**
     * @param string[] $requestedLocales
     */
    #[DataProvider('frenchRequestedLocale')]
    public function testGetBestWithFrench(array $requestedLocales): void
    {
        $locale = Locales::getBest($requestedLocales);

        $this->assertSame('fr_FR', $locale);
    }

    /**
     * @return string[][][]
     */
    public static function englishRequestedLocale(): array
    {
        return [
            [[]],
            [['']],
            [['àà']],
            [['en_GB']],
            [['en_US']],
            [['en_GB', 'fr_FR']],
            [['fr', 'en_GB']],
            [['de_DE']],
        ];
    }

    /**
     * @return string[][][]
     */
    public static function frenchRequestedLocale(): array
    {
        return [
            [['fr_FR']],
            [['fr_BE']],
            [['fr']],
            [['fr_FR', 'en_GB']],
            [['en', 'fr_FR']],
        ];
    }
}
