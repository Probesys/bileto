<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Utils;

use App\Utils\DomHelper;
use PHPUnit\Framework\TestCase;

class DomHelperTest extends TestCase
{
    public function testReplaceImagesUrls(): void
    {
        $content = '<img alt="" src="https://example.coop/image.jpg">';
        $mapping = [
            'https://example.coop/image.jpg' => 'cid:image.jpg',
        ];

        $newContent = DomHelper::replaceImagesUrls($content, $mapping);

        $newContent = trim($newContent);
        $this->assertSame('<img alt="" src="cid:image.jpg">', $newContent);
    }

    public function testReplaceImagesUrlsWithUnmatchingUrl(): void
    {
        $content = '<img alt="" src="/image.jpg">';
        $mapping = [
            'https://example.coop/image.jpg' => 'cid:image.jpg',
        ];

        $newContent = DomHelper::replaceImagesUrls($content, $mapping);

        $newContent = trim($newContent);
        $this->assertSame($content, $newContent);
    }

    public function testReplaceImagesUrlsWithNoImage(): void
    {
        $content = '<p>My content</p>';
        $mapping = [
            'https://example.coop/image.jpg' => 'cid:image.jpg',
        ];

        $newContent = DomHelper::replaceImagesUrls($content, $mapping);

        $newContent = trim($newContent);
        $this->assertSame($content, $newContent);
    }

    public function testReplaceImagesUrlsWithEmptyContent(): void
    {
        $content = '';
        $mapping = [
            'https://example.coop/image.jpg' => 'cid:image.jpg',
        ];

        $newContent = DomHelper::replaceImagesUrls($content, $mapping);

        $newContent = trim($newContent);
        $this->assertSame('', $newContent);
    }
}
