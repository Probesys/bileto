<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Command\Users;

use App\Tests\CommandTestsHelper;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CreateCommandTest extends KernelTestCase
{
    use Factories;
    use CommandTestsHelper;
    use ResetDatabase;

    public function testExecuteCreatesAUser(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix@example.com';
        $password = 'secret';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [], [
            $email,
            $password,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "The user \"{$email}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = UserFactory::first();
        $this->assertNotNull($user);
        $this->assertSame($email, $user->getEmail());
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $password));
        $this->assertSame('en_GB', $user->getLocale());
        $this->assertSame(20, strlen($user->getUid()));
        // It should also give the "super-admin" permissions to the user.
        $authorization = AuthorizationFactory::first();
        $this->assertSame($user->getId(), $authorization->getHolder()->getId());
        $this->assertSame('super', $authorization->getRole()->getType());
    }

    public function testExecuteWorksWhenPassingOptions(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix@example.com';
        $password = 'secret';
        $locale = 'fr_FR';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [
            '--email' => $email,
            '--password' => $password,
            '--locale' => $locale,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "The user \"{$email}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = UserFactory::first();
        $this->assertNotNull($user);
        $this->assertSame($email, $user->getEmail());
        $this->assertTrue($passwordHasher->isPasswordValid($user->_real(), $password));
        $this->assertSame($locale, $user->getLocale());
        $this->assertSame(20, strlen($user->getUid()));
    }

    public function testExecuteFailsIfEmailIsInvalid(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix';
        $password = 'secret';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame("Enter a valid email address.\n", $tester->getErrorOutput());
        $this->assertSame(0, UserFactory::count());
    }

    public function testExecuteFailsIfEmailIsEmpty(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = ' ';
        $password = 'secret';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "Enter an email address.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(0, UserFactory::count());
    }

    public function testExecuteFailsIfEmailExists(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix@example.com';
        $password = 'secret';
        UserFactory::createOne([
            'email' => $email,
        ]);

        $this->assertSame(1, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(
            "Enter a different email address, this one is already in use.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(1, UserFactory::count());
    }

    public function testExecuteFailsIfLocaleIsInvalid(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix@example.com';
        $password = 'secret';
        $locale = 'not a locale';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [
            '--email' => $email,
            '--password' => $password,
            '--locale' => $locale,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame("Select a language from the list.\n", $tester->getErrorOutput());
        $this->assertSame(0, UserFactory::count());
    }
}
