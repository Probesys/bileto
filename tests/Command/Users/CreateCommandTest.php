<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Command\Users;

use App\Factory\UserFactory;
use App\Tests\CommandTestsHelper;
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

        $tester = self::executeCommand('app:users:create', [
            $email,
            $password,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(
            "The user \"{$email}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = UserFactory::first();
        $this->assertNotNull($user);
        $this->assertSame($email, $user->getEmail());
        $this->assertTrue($passwordHasher->isPasswordValid($user->object(), $password));
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testExecuteWorksWhenPassingOptions(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix@example.com';
        $password = 'secret';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(
            "The user \"{$email}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = UserFactory::first();
        $this->assertNotNull($user);
        $this->assertSame($email, $user->getEmail());
        $this->assertTrue($passwordHasher->isPasswordValid($user->object(), $password));
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testExecuteFailsIfEmailIsInvalid(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix';
        $password = 'secret';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame(
            "The email \"{$email}\" is not a valid email.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(0, UserFactory::count());
    }

    public function testExecuteFailsIfEmailIsEmpty(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = ' ';
        $password = 'secret';

        $this->assertSame(0, UserFactory::count());

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame(
            "The email is required.\n",
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

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame(
            "The email \"{$email}\" is already used.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(1, UserFactory::count());
    }
}
