<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IconExtension extends AbstractExtension
{
    private string $pathToIcons = '';

    public function __construct(string $pathToIcons)
    {
        $this->pathToIcons = $pathToIcons;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'icon',
                [$this, 'icon'],
                ['is_safe' => ['html']]
            ),
        ];
    }

    public function icon(string $iconName): string
    {
        $iconFilename = "{$this->pathToIcons}/{$iconName}.svg";
        $iconContent = @file_get_contents($iconFilename);
        if ($iconContent === false) {
            throw new \Exception("Icon {$iconName} does not exist.");
        }

        return <<<HTML
            <span class="icon icon--{$iconName}" aria-hidden="true">{$iconContent}</span>
        HTML;
    }
}
