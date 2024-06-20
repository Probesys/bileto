<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Utils;

use App\Utils\Email;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function testExtractDomain(): void
    {
        $email = 'alix@example.com';

        $domain = Email::extractDomain($email);

        $this->assertEquals('example.com', $domain);
    }

    public function testExtractDomainWithTwoAts(): void
    {
        $email = 'alix@test@example.com';

        $domain = Email::extractDomain($email);

        $this->assertEquals('example.com', $domain);
    }
}
