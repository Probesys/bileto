<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

trait CommandTestsHelper
{
    /**
     * @var \Symfony\Bundle\FrameworkBundle\Console\Application
     */
    protected static $application;

    /**
     * @beforeClass
     */
    public static function setUpConsoleTestsHelper(): void
    {
        self::bootKernel();
        self::$application = new Application(self::$kernel);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string> $inputs
     */
    protected static function executeCommand(
        string $command,
        array $args = [],
        array $inputs = [],
    ): CommandTester {
        $command = self::$application->find($command);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute($args, [
            'interactive' => !empty($inputs),
            'capture_stderr_separately' => true,
        ]);
        return $commandTester;
    }
}
