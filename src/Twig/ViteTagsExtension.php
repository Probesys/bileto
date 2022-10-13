<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ViteTagsExtension extends AbstractExtension
{
    /** @var array<string, array<string, string>> $manifest */
    private array $manifest = [];

    private Packages $assetPackages;

    public function __construct(string $pathToViteManifest, Packages $assetPackages)
    {
        $manifestContent = @file_get_contents($pathToViteManifest);
        if (!$manifestContent) {
            return;
        }

        $manifestJson = json_decode($manifestContent, true);
        if (!$manifestJson) {
            return;
        }

        $this->manifest = $manifestJson;
        $this->assetPackages = $assetPackages;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'vite_javascript_tag',
                [$this, 'renderJavascriptTag'],
                ['is_safe' => ['html']]
            ),
            new TwigFunction(
                'vite_stylesheet_tag',
                [$this, 'renderStylesheetTag'],
                ['is_safe' => ['html']]
            ),
        ];
    }

    public function renderJavascriptTag(string $entryName): string
    {
        $filename = $this->getFilename($entryName);
        if (!$filename) {
            return '';
        }

        $assetUrl = $this->assetPackages->getUrl($filename);
        return <<<HTML
            <script src="{$assetUrl}"></script>
        HTML;
    }

    public function renderStylesheetTag(string $entryName): string
    {
        $filename = $this->getFilename($entryName);
        if (!$filename) {
            return '';
        }

        $assetUrl = $this->assetPackages->getUrl($filename);
        return <<<HTML
            <link rel="stylesheet" href="{$assetUrl}">
        HTML;
    }

    private function getFilename(string $entryName): ?string
    {
        if (!$this->manifest) {
            return null;
        }

        if (!isset($this->manifest[$entryName])) {
            return null;
        }

        if (!isset($this->manifest[$entryName]['file'])) {
            return null;
        }

        return $this->manifest[$entryName]['file'];
    }
}
