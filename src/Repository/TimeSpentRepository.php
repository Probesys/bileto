<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\TimeSpent;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeSpent>
 *
 * @method TimeSpent|null find($id, $lockMode = null, $lockVersion = null)
 * @method TimeSpent|null findOneBy(array $criteria, array $orderBy = null)
 * @method TimeSpent[]    findAll()
 * @method TimeSpent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TimeSpentRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeSpent::class);
    }

    public function save(TimeSpent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param TimeSpent[] $entities
     */
    public function saveBatch(array $entities, bool $flush = false): void
    {
        foreach ($entities as $entity) {
            $this->save($entity, false);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TimeSpent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
