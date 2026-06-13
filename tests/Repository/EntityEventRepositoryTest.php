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

class EntityEventRepositoryTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testFindLastActivityAtForUserReturnsNullWhenUserHasNoEvents(): void
    {
        /** @var Repository\EntityEventRepository */
        $entityEventRepository = Factory\EntityEventFactory::repository();
        $user = Factory\UserFactory::createOne();

        $lastActivityAt = $entityEventRepository->findLastActivityAtForUser($user->_real());

        $this->assertNull($lastActivityAt);
    }

    public function testFindLastActivityAtForUserReturnsMostRecentCreatedAt(): void
    {
        /** @var Repository\EntityEventRepository */
        $entityEventRepository = Factory\EntityEventFactory::repository();
        $user = Factory\UserFactory::createOne();
        $expectedDate = Utils\Time::ago(1, 'day');
        Factory\EntityEventFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'weeks'),
            'createdBy' => $user,
            'entityType' => Entity\Ticket::class,
            'entityId' => -1,
            'type' => 'insert',
        ]);
        Factory\EntityEventFactory::createOne([
            'createdAt' => $expectedDate,
            'createdBy' => $user,
            'entityType' => Entity\Ticket::class,
            'entityId' => -2,
            'type' => 'insert',
        ]);
        Factory\EntityEventFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'week'),
            'createdBy' => $user,
            'entityType' => Entity\Ticket::class,
            'entityId' => -3,
            'type' => 'update',
        ]);

        $lastActivityAt = $entityEventRepository->findLastActivityAtForUser($user->_real());

        $this->assertNotNull($lastActivityAt);
        $this->assertSame($expectedDate->getTimestamp(), $lastActivityAt->getTimestamp());
    }

    public function testFindLastActivityAtForUserIgnoresEventsCreatedByOtherUsers(): void
    {
        /** @var Repository\EntityEventRepository */
        $entityEventRepository = Factory\EntityEventFactory::repository();
        $userA = Factory\UserFactory::createOne();
        $userB = Factory\UserFactory::createOne();
        $userBDate = Utils\Time::ago(2, 'weeks');
        Factory\EntityEventFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'hour'),
            'createdBy' => $userA,
            'entityType' => Entity\Ticket::class,
            'entityId' => -1,
            'type' => 'insert',
        ]);
        Factory\EntityEventFactory::createOne([
            'createdAt' => $userBDate,
            'createdBy' => $userB,
            'entityType' => Entity\Ticket::class,
            'entityId' => -2,
            'type' => 'insert',
        ]);

        $lastActivityAt = $entityEventRepository->findLastActivityAtForUser($userB->_real());

        $this->assertNotNull($lastActivityAt);
        $this->assertSame($userBDate->getTimestamp(), $lastActivityAt->getTimestamp());
    }

    public function testFindLastActivityAtForUserIgnoresEventsWhereUserIsOnlyUpdatedBy(): void
    {
        /** @var Repository\EntityEventRepository */
        $entityEventRepository = Factory\EntityEventFactory::repository();
        $userA = Factory\UserFactory::createOne();
        $userB = Factory\UserFactory::createOne();
        Factory\EntityEventFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'day'),
            'createdBy' => $userA,
            'updatedBy' => $userB,
            'entityType' => Entity\Ticket::class,
            'entityId' => -1,
            'type' => 'update',
        ]);

        $lastActivityAt = $entityEventRepository->findLastActivityAtForUser($userB->_real());

        $this->assertNull($lastActivityAt);
    }
}
