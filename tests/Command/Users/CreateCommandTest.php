<?php

namespace App\Tests\Command\Users;

use App\Entity;
use App\Tests;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;

class CreateCommandTest extends KernelTestCase
{
    use Tests\CommandTestsHelper;
    use Tests\EntityManagerHelper;
    use Tests\DatabaseResetterHelper;

    public function testExecuteCreatesAUser(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $userRepository = self::getRepository(Entity\User::class);
        $email = 'alix@example.com';
        $password = 'secret';

        $this->assertSame(0, $userRepository->count([]));

        $tester = self::executeCommand('app:users:create', [
            $email,
            $password,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(
            "The user \"{$email}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = $userRepository->findOneBy([]);
        $this->assertNotNull($user);
        $this->assertSame($email, $user->getEmail());
        $this->assertTrue($passwordHasher->isPasswordValid($user, $password));
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testExecuteWorksWhenPassingOptions(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $userRepository = self::getRepository(Entity\User::class);
        $email = 'alix@example.com';
        $password = 'secret';

        $this->assertSame(0, $userRepository->count([]));

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(
            "The user \"{$email}\" has been created.\n",
            $tester->getDisplay()
        );
        $user = $userRepository->findOneBy([]);
        $this->assertNotNull($user);
        $this->assertSame($email, $user->getEmail());
        $this->assertTrue($passwordHasher->isPasswordValid($user, $password));
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testExecuteFailsIfEmailIsInvalid(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $userRepository = self::getRepository(Entity\User::class);
        $email = 'alix';
        $password = 'secret';

        $this->assertSame(0, $userRepository->count([]));

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame(
            "The email \"{$email}\" is not a valid email.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(0, $userRepository->count([]));
    }

    public function testExecuteFailsIfEmailIsEmpty(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $userRepository = self::getRepository(Entity\User::class);
        $email = ' ';
        $password = 'secret';

        $this->assertSame(0, $userRepository->count([]));

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame(
            "The email is required.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(0, $userRepository->count([]));
    }

    public function testExecuteFailsIfEmailExists(): void
    {
        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $userRepository = self::getRepository(Entity\User::class);
        $email = 'alix@example.com';
        $password = 'secret';

        $user = new Entity\User();
        $user->setEmail($email);
        $user->setPassword('');
        self::$entityManager->persist($user);
        self::$entityManager->flush();

        $this->assertSame(1, $userRepository->count([]));

        $tester = self::executeCommand('app:users:create', [], [
            '--email' => $email,
            '--password' => $password,
        ]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertSame(
            "The email \"{$email}\" is already used.\n",
            $tester->getErrorOutput()
        );
        $this->assertSame(1, $userRepository->count([]));
    }
}
