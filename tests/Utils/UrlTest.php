<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Utils;

use App\Utils\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    public function testSanitizeDomain(): void
    {
        $domain = 'example.com';

        $sanitizedDomain = Url::sanitizeDomain($domain);

        $this->assertEquals('example.com', $sanitizedDomain);
    }

    public function testSanitizeDomainTrimsSpaces(): void
    {
        $domain = ' example.com ';

        $sanitizedDomain = Url::sanitizeDomain($domain);

        $this->assertEquals('example.com', $sanitizedDomain);
    }

    public function testSanitizeDomainLowercasesTheDomain(): void
    {
        $domain = 'EXAMPLE.com';

        $sanitizedDomain = Url::sanitizeDomain($domain);

        $this->assertEquals('example.com', $sanitizedDomain);
    }

    public function testSanitizeDomainConvertsDomainToAscii(): void
    {
        $domain = 'éxample.com';

        $sanitizedDomain = Url::sanitizeDomain($domain);

        $this->assertEquals('xn--xample-9ua.com', $sanitizedDomain);
    }

    public function testDomainToUtf8(): void
    {
        $domain = 'xn--xample-9ua.com';

        $utf8Domain = Url::domainToUtf8($domain);

        $this->assertEquals('éxample.com', $utf8Domain);
    }
}
