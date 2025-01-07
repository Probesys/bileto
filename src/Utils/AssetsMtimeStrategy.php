<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class AssetsMtimeStrategy implements VersionStrategyInterface
{
    public function __construct(
        private string $publicPath,
    ) {
    }

    public function getVersion(string $path): string
    {
        $fullpath = "{$this->publicPath}/{$path}";
        $modicationTime = @filemtime($fullpath);
        if ($modicationTime) {
            return (string) $modicationTime;
        } else {
            return '';
        }
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);
        return "{$path}?v={$version}";
    }
}
