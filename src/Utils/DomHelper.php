<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils;

class DomHelper
{
    /**
     * Replace the images URLs in a DOM string.
     *
     * The content string must be a valid DOM element.
     *
     * The mapping variable contains a list of URLs to replace, where the keys
     * are the URLs in the DOM and the values are the new URLs.
     *
     * @param array<string, string> $mapping
     */
    public static function replaceImagesUrls(string $content, array $mapping): string
    {
        if (!$content) {
            return '';
        }

        $contentDom = new \DOMDocument();

        // DOMDocument::loadHTML considers the source string to be encoded in
        // ISO-8859-1 by default. In order to not ending with weird characters,
        // we encode the non-ASCII chars (i.e. all chars above >0x80) to HTML
        // entities.
        $content = mb_encode_numericentity(
            $content,
            [0x80, 0x10FFFF, 0, -1],
            'UTF-8'
        );

        $libxmlOptions = \LIBXML_NOERROR | \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD;
        $contentDom->loadHTML($content, $libxmlOptions);
        $contentDomXPath = new \DomXPath($contentDom);

        foreach ($mapping as $initialUrl => $newUrl) {
            $imageNodes = $contentDomXPath->query("//img[@src='{$initialUrl}']");

            if ($imageNodes === false || $imageNodes->length === 0) {
                // no corresponding node, the URL doesn't appear in the content
                continue;
            }

            foreach ($imageNodes as $imageNode) {
                if ($imageNode instanceof \DOMElement) {
                    $imageNode->setAttribute('src', $newUrl);
                }
            }
        }

        $result = $contentDom->saveHTML($contentDom->documentElement);

        if ($result === false) {
            return $content;
        }

        return $result;
    }
}
