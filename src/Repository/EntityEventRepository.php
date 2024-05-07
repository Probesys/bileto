<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\EntityEvent;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EntityEvent>
 *
 * @method EntityEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method EntityEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method EntityEvent[]    findAll()
 * @method EntityEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntityEventRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<EntityEvent> */
    use CommonTrait;
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityEvent::class);
    }
}
