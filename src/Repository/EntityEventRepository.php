<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\ActivityMonitor;
use App\Entity;
use App\Uid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entity\EntityEvent>
 */
class EntityEventRepository extends ServiceEntityRepository implements Uid\UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Entity\EntityEvent> */
    use CommonTrait;
    use Uid\UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\EntityEvent::class);
    }

    public function removeByEntity(ActivityMonitor\RecordableEntityInterface $entity): int
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            DELETE App\Entity\EntityEvent ee
            WHERE ee.entityType = :entityType
            AND ee.entityId = :entityId
        SQL);

        $query->setParameter('entityType', $entity->getEntityType());
        $query->setParameter('entityId', $entity->getId());

        return $query->execute();
    }
}
