<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service\UserCreator;
use App\Service\UserCreatorException;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
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
            organization: $organization->_real(),
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

    public function testCreateCanGrantAccessToDomainOrganization(): void
    {
        $email = 'alix@example.com';
        $role = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);
        $organization = OrganizationFactory::createOne([
            'domains' => ['example.com'],
        ]);

        $this->assertSame(0, UserFactory::count());
        $this->assertSame(0, AuthorizationFactory::count());

        $user = $this->userCreator->create(
            email: $email,
        );

        $this->assertSame(1, UserFactory::count());
        $this->assertSame($email, $user->getEmail());

        $this->assertSame(1, AuthorizationFactory::count());
        $authorization = AuthorizationFactory::last();
        $authHolder = $authorization->getHolder();
        $authRole = $authorization->getRole();
        $authOrganization = $authorization->getOrganization();
        $this->assertSame($user->getUid(), $authHolder->getUid());
        $this->assertSame($role->getUid(), $authRole->getUid());
        $this->assertNotNull($authOrganization);
        $this->assertSame($organization->getUid(), $authOrganization->getUid());
    }

    public function testCreateCanGrantAccessToDefaultOrganization(): void
    {
        $email = 'alix@example.com';
        $role = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);
        $organization = OrganizationFactory::createOne([
            'domains' => ['example.com'],
        ]);
        $defaultOrganization = OrganizationFactory::createOne([
            'domains' => [],
        ]);

        $this->assertSame(0, UserFactory::count());
        $this->assertSame(0, AuthorizationFactory::count());

        $user = $this->userCreator->create(
            email: $email,
            organization: $defaultOrganization->_real(),
        );

        $this->assertSame(1, UserFactory::count());
        $this->assertSame($email, $user->getEmail());

        $this->assertSame(1, AuthorizationFactory::count());
        $authorization = AuthorizationFactory::last();
        $authHolder = $authorization->getHolder();
        $authRole = $authorization->getRole();
        $authOrganization = $authorization->getOrganization();
        $this->assertSame($user->getUid(), $authHolder->getUid());
        $this->assertSame($role->getUid(), $authRole->getUid());
        $this->assertNotNull($authOrganization);
        $this->assertSame($defaultOrganization->getUid(), $authOrganization->getUid());
    }

    public function testCreateDoesNotFlushAuthorizationsIfFlushIsFalse(): void
    {
        $email = 'alix@example.com';
        $role = RoleFactory::createOne([
            'type' => 'user',
            'isDefault' => true,
        ]);
        $organization = OrganizationFactory::createOne();

        $this->assertSame(0, UserFactory::count());
        $this->assertSame(0, AuthorizationFactory::count());

        $user = $this->userCreator->create(
            email: $email,
            organization: $organization->_real(),
            flush: false,
        );

        $this->assertSame(0, UserFactory::count());
        $this->assertSame(0, AuthorizationFactory::count());
        $authorizations = $user->getAuthorizations();
        $this->assertSame(1, count($authorizations));
        $authHolder = $authorizations[0]->getHolder();
        $authRole = $authorizations[0]->getRole();
        $authOrganization = $authorizations[0]->getOrganization();
        $this->assertSame($user->getUid(), $authHolder->getUid());
        $this->assertSame($role->getUid(), $authRole->getUid());
        $this->assertNotNull($authOrganization);
        $this->assertSame($organization->getUid(), $authOrganization->getUid());
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
