<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use App\Uid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entity\SessionLog>
 */
class SessionLogRepository extends ServiceEntityRepository implements Uid\UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Entity\SessionLog> */
    use CommonTrait;
    use Uid\UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\SessionLog::class);
    }

    /**
     * @return Entity\SessionLog[]
     */
    public function findByIdentifier(string $identifier): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT sl
            FROM App\Entity\SessionLog sl
            WHERE sl.identifier = :identifier
            ORDER BY sl.createdAt ASC
        SQL);

        $query->setParameter('identifier', $identifier);

        return $query->getResult();
    }

    public function removeOlderThan(\DateTimeInterface $date): int
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            DELETE App\Entity\SessionLog sl
            WHERE sl.createdAt <= :date
        SQL);

        $query->setParameter('date', $date);

        return $query->execute();
    }

    public function removeByIdentifier(string $identifier): int
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            DELETE App\Entity\SessionLog sl
            WHERE sl.identifier <= :identifier
        SQL);

        $query->setParameter('identifier', $identifier);

        return $query->execute();
    }
}
