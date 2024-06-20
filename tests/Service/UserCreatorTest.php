<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service\UserCreator;
use App\Service\UserCreatorException;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserCreatorTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private UserCreator $userCreator;

    #[Before]
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var UserCreator */
        $userCreator = $container->get(UserCreator::class);
        $this->userCreator = $userCreator;
    }

    public function testCreate(): void
    {
        $email = 'alix@example.com';

        $this->assertSame(0, UserFactory::count());

        $user = $this->userCreator->create(
            email: $email,
        );

        $this->assertSame(1, UserFactory::count());
        $this->assertSame($email, $user->getEmail());
    }

    public function testCreateCanSetAdditionalParameters(): void
    {
        /** @var UserPasswordHasherInterface */
        $passwordHasher = self::getContainer()->get('security.user_password_hasher');
        $email = 'alix@example.com';
        $name = 'Alix Hambourg';
        $password = 'secret';
        $locale = 'fr_FR';
        $ldapIdentifier = 'alix';
        $organization = OrganizationFactory::createOne();

        $this->assertSame(0, UserFactory::count());

        $user = $this->userCreator->create(
            email: $email,
            name: $name,
            password: $password,
            locale: $locale,
            ldapIdentifier: $ldapIdentifier,
            organization: $organization->object(),
        );

        $this->assertSame(1, UserFactory::count());
        $this->assertSame($email, $user->getEmail());
        $this->assertSame($name, $user->getName());
        $this->assertTrue($passwordHasher->isPasswordValid($user, $password));
        $this->assertSame($locale, $user->getLocale());
        $this->assertSame($ldapIdentifier, $user->getLdapIdentifier());
        $userOrganization = $user->getOrganization();
        $this->assertNotNull($userOrganization);
        $this->assertSame($organization->getUid(), $userOrganization->getUid());
    }

    public function testCreateFailsOnError(): void
    {
        $this->expectException(UserCreatorException::class);

        $email = 'not an email';

        $this->assertSame(0, UserFactory::count());

        $user = $this->userCreator->create(
            email: $email,
        );

        $this->assertSame(0, UserFactory::count());
    }
}
