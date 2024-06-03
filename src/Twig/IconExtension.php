<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IconExtension extends AbstractExtension
{
    private string $pathToIcons = '';

    private Packages $assetPackages;

    public function __construct(string $pathToIcons, Packages $assetPackages)
    {
        $this->pathToIcons = $pathToIcons;
        $this->assetPackages = $assetPackages;
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

    public function icon(string $iconName, string $additionalClassNames = ''): string
    {
        $iconName = htmlspecialchars($iconName);
        $additionalClassNames = htmlspecialchars($additionalClassNames);

        $modificationTime = @filemtime($this->pathToIcons);
        $iconsUrl = $this->assetPackages->getUrl('icons.svg');

        $classNames = "icon icon--{$iconName}";
        if ($additionalClassNames) {
            $classNames .= " {$additionalClassNames}";
        }

        $svg = "<svg class=\"{$classNames}\" aria-hidden=\"true\" width=\"24\" height=\"24\">";
        $svg .= "<use xlink:href=\"{$iconsUrl}?{$modificationTime}#{$iconName}\"/>";
        $svg .= '</svg>';
        return $svg;
    }
}
