<?php

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
     * @param string $command
     * @param array<string> $inputs
     * @param array<string> $args
     */
    protected static function executeCommand(
        string $command,
        array $inputs = [],
        array $args = [],
    ): CommandTester {
        $command = self::$application->find($command);
        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute($args, [
            'capture_stderr_separately' => true,
        ]);
        return $commandTester;
    }
}
