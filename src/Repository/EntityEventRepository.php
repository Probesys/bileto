<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\ActivityMonitor;
use App\Entity;
use App\Uid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    /**
     * Remove entity events that are associated to deleted entities.
     *
     * This method doesn't delete entity events more recent than the given
     * date, nor the `delete` events. These both restrictions allow to keep a
     * good traceability of deleted entities in case of an attack. Indeed, an
     * attacker that would delete data quietly couldn't completely erase his
     * traces.
     *
     * It returns the number of deleted entity events.
     */
    public function removeExpiredOlderThan(\DateTimeInterface $date): int
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT DISTINCT ee.entityType FROM App\Entity\EntityEvent ee
        SQL);

        $count = 0;
        foreach ($query->getSingleColumnResult() as $entityType) {
            $count += $this->removeExpiredOlderThanByEntityType($entityType, $date);
        }

        return $count;
    }

    private function removeExpiredOlderThanByEntityType(
        string $entityType,
        \DateTimeInterface $date,
    ): int {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        if (!class_exists($entityType)) {
            // An entity class has probably been removed or renamed. In this
            // case, we delete all the events as we consider that the
            // corresponding entities have been deleted and are no longer
            // pertinent.
            // Note that in case of a renamed entity, the ideal is to change
            // the `EntityEvent::entityType` values as well.

            $deleteQuery = $entityManager->createQuery(<<<SQL
                DELETE App\Entity\EntityEvent ee
                WHERE ee.entityType = :entityType
            SQL);

            $deleteQuery->setParameter('entityType', $entityType);

            return $deleteQuery->execute();
        }

        try {
            $entityTableName = $entityManager->getClassMetadata($entityType)->getTableName();
            $entityTableName = $connection->quoteIdentifier($entityTableName);
        } catch (\Exception $e) {
            throw new \LogicException("Cannot load class metadata of {$entityType} class");
        }

        // Return the list of event ids associated to deleted entities. The
        // trick lies in the `LEFT JOIN / ON` and `e.id IS NULL` condition.
        // The two last check allows to make sure to keep enough events for
        // traceability in case of an attack.
        // Note that we concatenate the table name value directly to the SQL
        // query. This is generally a bad idea as it allows SQL injection
        // attacks. However, we can't inject the value as a named parameter
        // because it wouldn't work with a table name. Here, we are safe
        // because the $entityTableName value is determined based on the entity
        // class and is not based on some user input.
        $entityEventIds = $connection->fetchFirstColumn(<<<SQL
            SELECT DISTINCT ee.id FROM entity_event ee
            LEFT JOIN {$entityTableName} e
            ON ee.entity_id = e.id
            WHERE ee.entity_type = :entityType
            AND e.id IS NULL
            AND ee.type != 'delete'
            AND ee.created_at < :date
        SQL, [
            'entityType' => $entityType,
            'date' => $date,
        ], [
            'entityType' => Types::STRING,
            'date' => Types::DATETIMETZ_IMMUTABLE,
        ]);

        $deleteQuery = $entityManager->createQuery(<<<SQL
            DELETE App\Entity\EntityEvent ee
            WHERE ee.id IN (:ids)
        SQL);

        $deleteQuery->setParameter('ids', $entityEventIds);

        return $deleteQuery->execute();
    }
}
