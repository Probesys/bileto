<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service;
use App\Tests;
use App\Tests\Factory\AuthorizationFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\RoleFactory;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserServiceTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;

    private Service\UserService $userService;

    #[Before]
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var Service\UserService */
        $userService = $container->get(Service\UserService::class);
        $this->userService = $userService;
    }

    public function testGetDefaultOrganizationWithDeclaredDefaultOrganizationAndPermission(): void
    {
        $organization1 = OrganizationFactory::createOne();
        $organization2 = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization1,
        ]);
        $this->grantOrga(
            $user->_real(),
            ['orga:create:tickets'],
            $organization1->_real(),
            'user',
        );

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertSame($organization1->getId(), $defaultOrganization?->getId());
    }

    public function testGetDefaultOrganizationWithOrganizationDomainAndPermission(): void
    {
        $organization1 = OrganizationFactory::createOne([
            'domains' => ['example.com'],
        ]);
        $organization2 = OrganizationFactory::createOne([
            'domains' => ['example.org'],
        ]);
        $user = UserFactory::createOne([
            'email' => 'alix@example.com',
            'organization' => null,
        ]);
        $this->grantOrga(
            $user->_real(),
            ['orga:create:tickets'],
            $organization1->_real(),
            'user',
        );

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertSame($organization1->getId(), $defaultOrganization?->getId());
    }

    public function testGetDefaultOrganizationWithGivenUserAuthorization(): void
    {
        $organization1 = OrganizationFactory::createOne();
        $organization2 = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => null,
        ]);
        $this->grantOrga(
            $user->_real(),
            ['orga:create:tickets'],
            $organization1->_real(),
            'user',
        );

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertSame($organization1->getId(), $defaultOrganization?->getId());
    }

    public function testGetDefaultOrganizationWithDeclaredDefaultOrganizationAndPermissionOnOtherOrganization(): void
    {
        $organization1 = OrganizationFactory::createOne();
        $organization2 = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization1,
        ]);
        $this->grantOrga(
            $user->_real(),
            ['orga:create:tickets'],
            $organization2->_real(),
            'user',
        );

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertSame($organization2->getId(), $defaultOrganization?->getId());
    }

    public function testGetDefaultOrganizationWithOrganizationDomainAndPermissionOnOtherOrganization(): void
    {
        $organization1 = OrganizationFactory::createOne([
            'domains' => ['example.com'],
        ]);
        $organization2 = OrganizationFactory::createOne([
            'domains' => ['example.org'],
        ]);
        $user = UserFactory::createOne([
            'email' => 'alix@example.com',
            'organization' => null,
        ]);
        $this->grantOrga(
            $user->_real(),
            ['orga:create:tickets'],
            $organization2->_real(),
            'user',
        );

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertSame($organization2->getId(), $defaultOrganization?->getId());
    }

    public function testGetDefaultOrganizationReturnsNullWithDeclaredDefaultOrganizationAndNoPermission(): void
    {
        $organization1 = OrganizationFactory::createOne();
        $organization2 = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => $organization1,
        ]);

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertNull($defaultOrganization);
    }

    public function testGetDefaultOrganizationReturnsNullWithOrganizationDomainAndNoPermission(): void
    {
        $organization1 = OrganizationFactory::createOne([
            'domains' => ['example.com'],
        ]);
        $organization2 = OrganizationFactory::createOne([
            'domains' => ['example.org'],
        ]);
        $user = UserFactory::createOne([
            'email' => 'alix@example.com',
            'organization' => null,
        ]);

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertNull($defaultOrganization);
    }

    public function testGetDefaultOrganizationReturnsNullWithAgentAuthorization(): void
    {
        $organization1 = OrganizationFactory::createOne();
        $organization2 = OrganizationFactory::createOne();
        $user = UserFactory::createOne([
            'organization' => null,
        ]);
        $this->grantOrga(
            $user->_real(),
            ['orga:create:tickets'],
            $organization1->_real(),
            'agent',
        );

        $defaultOrganization = $this->userService->getDefaultOrganization($user->_real());

        $this->assertNull($defaultOrganization);
    }
}
