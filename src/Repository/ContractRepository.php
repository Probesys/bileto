<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Organization;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use App\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
 *
 * @method Contract|null find($id, $lockMode = null, $lockVersion = null)
 * @method Contract|null findOneBy(array $criteria, array $orderBy = null)
 * @method Contract[]    findAll()
 * @method Contract[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContractRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Contract> */
    use CommonTrait;
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    /**
     * @return Contract[]
     */
    public function findOngoingByOrganization(Organization $organization): array
    {
        $entityManager = $this->getEntityManager();

        $now = Utils\Time::now();

        $query = $entityManager->createQuery(<<<SQL
            SELECT c
            FROM App\Entity\Contract c
            LEFT JOIN c.timeSpents ts

            WHERE c.startAt <= :now
            AND :now < c.endAt
            AND c.organization = :organization

            GROUP BY c.id
            HAVING c.maxHours > (COALESCE(SUM(ts.time), 0) / 60.0)
        SQL);

        $query->setParameter('now', $now);
        $query->setParameter('organization', $organization);

        return $query->getResult();
    }
}
