<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

class SettingsExtension
{
    public function __construct(
        private string $customLogoPathname,
    ) {
    }

    #[AsTwigFunction('logo', isSafe: ['html'])]
    public function logo(): string
    {
        if (file_exists($this->customLogoPathname)) {
            return '@settings/logo.svg';
        } else {
            return '@public/logo.svg';
        }
    }
}
