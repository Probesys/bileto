<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Command;

use App\Tests\CommandTestsHelper;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SeedsCommandTest extends KernelTestCase
{
    use CommandTestsHelper;
    use Factories;
    use ResetDatabase;

    public function testExecute(): void
    {
        $tester = self::executeCommand('db:seeds:load');

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(3, RoleFactory::count());
        $this->assertSame(4, OrganizationFactory::count());
        $this->assertSame(3, UserFactory::count());
    }
}