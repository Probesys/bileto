<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Organization;
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
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    public function save(Contract $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Contract $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Contract[]
     */
    public function findOngoingByOrganization(Organization $organization): array
    {
        $entityManager = $this->getEntityManager();

        $now = Utils\Time::now();
        $orgaIds = $organization->getParentOrganizationIds();
        $orgaIds[] = $organization->getId();

        $query = $entityManager->createQuery(<<<SQL
            SELECT c
            FROM App\Entity\Contract c
            LEFT JOIN c.timeSpents ts

            WHERE c.startAt <= :now
            AND :now < c.endAt
            AND c.organization IN (:orgaIds)

            GROUP BY c.id
            HAVING c.maxHours > (COALESCE(SUM(ts.time), 0) / 60.0)
        SQL);

        $query->setParameter('now', $now);
        $query->setParameter('orgaIds', $orgaIds);

        return $query->getResult();
    }
}
