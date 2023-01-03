<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Command;

use App\Tests\CommandTestsHelper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;

class SecretCommandTest extends KernelTestCase
{
    use CommandTestsHelper;

    public function testExecuteReturnsAString(): void
    {
        $tester = self::executeCommand('app:secret');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(32, strlen(trim($tester->getDisplay())));
    }
}
