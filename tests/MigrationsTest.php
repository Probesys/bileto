<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Zenstruck\Foundry\Test\ResetDatabase;

class MigrationsTest extends KernelTestCase
{
    use CommandTestsHelper;
    use ResetDatabase;

    public static function setUpBeforeClass(): void
    {
        // We need to disable DoctrineTestBundle for this test, or it fails
        // when using MariaDB, with "Exception in third-party event subscriber:
        // There is no active transaction".
        StaticDriver::setKeepStaticConnections(false);
    }

    public static function tearDownAfterClass(): void
    {
        StaticDriver::setKeepStaticConnections(true);
    }

    public function testMigrationsUpWork(): void
    {
        // erase the database structure
        $tester = self::executeCommand('doctrine:schema:drop', [
            '--force' => true
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());

        // and apply the migrations one by one
        $tester = self::executeCommand('doctrine:migrations:migrate', [
            '--no-interaction' => true
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString(
            '[OK] Successfully migrated to version',
            $tester->getDisplay(),
        );
    }
}
