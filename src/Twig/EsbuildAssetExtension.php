<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Utils;
use Symfony\Component\Asset;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class EsbuildAssetExtension extends AbstractExtension
{
    public function __construct(
        private string $pathToAssets,
        #[Autowire('%app.public_directory%')]
        private string $pathToPublic,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('esbuild_asset', [$this, 'esbuildAsset']),
        ];
    }

    public function esbuildAsset(string $assetPath): string
    {
        $assetStrategy = new Utils\AssetsMtimeStrategy($this->pathToPublic);
        $assetPackage = new Asset\Package($assetStrategy);

        $assetPathname = "/{$this->pathToAssets}/{$assetPath}";

        return $assetPackage->getUrl($assetPathname);
    }
}
