<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Repository;

use App\Entity;
use App\Repository;
use App\Tests\Factory;
use App\Utils;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserRepositoryTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFindInactiveReturnsEmptyWhenThresholdIsZero(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);

        $inactiveUsers = $userRepository->findInactive(0);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveReturnsEmptyWhenThresholdIsNegative(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);

        $inactiveUsers = $userRepository->findInactive(-5);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveExcludesAnonymizedUsers(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
            'anonymizedAt' => Utils\Time::ago(1, 'day'),
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveExcludesUsersWithAdminAuthorization(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $user = Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);
        $role = Factory\RoleFactory::createOne(['type' => 'admin']);
        Factory\AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $role,
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveExcludesUsersWithAgentAuthorization(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $user = Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);
        $role = Factory\RoleFactory::createOne(['type' => 'agent']);
        Factory\AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $role,
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveExcludesUsersWithSuperAuthorization(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $user = Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);
        $role = Factory\RoleFactory::createOne(['type' => 'super']);
        Factory\AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $role,
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveListsUsersWithOnlyUserAuthorizationAndOldActivity(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $user = Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);
        $role = Factory\RoleFactory::createOne(['type' => 'user']);
        Factory\AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $role,
        ]);
        Factory\EntityEventFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
            'createdBy' => $user,
            'entityType' => Entity\Ticket::class,
            'entityId' => -1,
            'type' => 'insert',
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertCount(1, $inactiveUsers);
        $this->assertSame($user->getId(), $inactiveUsers[0]->getId());
    }

    public function testFindInactiveExcludesUsersWithOnlyUserAuthorizationAndRecentActivity(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $user = Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(3, 'months'),
        ]);
        $role = Factory\RoleFactory::createOne(['type' => 'user']);
        Factory\AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $role,
        ]);
        Factory\EntityEventFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'month'),
            'createdBy' => $user,
            'entityType' => Entity\Ticket::class,
            'entityId' => -1,
            'type' => 'insert',
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveListsUsersWithNoAuthorizationAndOldCreatedAt(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $user = Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertCount(1, $inactiveUsers);
        $this->assertSame($user->getId(), $inactiveUsers[0]->getId());
    }

    public function testFindInactiveExcludesUsersWithNoAuthorizationAndRecentCreatedAt(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(3, 'months'),
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertEmpty($inactiveUsers);
    }

    public function testFindInactiveExcludesUsersWithOnlyUserAuthorizationButRecentEvents(): void
    {
        /** @var Repository\UserRepository */
        $userRepository = Factory\UserFactory::repository();
        $user = Factory\UserFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'years'),
        ]);
        $role = Factory\RoleFactory::createOne(['type' => 'user']);
        Factory\AuthorizationFactory::createOne([
            'holder' => $user,
            'role' => $role,
        ]);
        Factory\EntityEventFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'month'),
            'createdBy' => $user,
            'entityType' => Entity\Ticket::class,
            'entityId' => -1,
            'type' => 'insert',
        ]);

        $inactiveUsers = $userRepository->findInactive(12);

        $this->assertEmpty($inactiveUsers);
    }
}
