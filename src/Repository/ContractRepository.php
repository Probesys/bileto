<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Organization;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use App\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
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
     * @return ORM\Query<Contract>
     */
    public function findByOrganizationQuery(Organization $organization): ORM\Query
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT c
            FROM App\Entity\Contract c

            WHERE c.organization = :organization

            ORDER BY c.endAt DESC, c.name
        SQL);

        $query->setParameter('organization', $organization);

        return $query;
    }

    /**
     * @param Organization[] $organizations
     * @return ORM\Query<Contract>
     */
    public function findOngoingByOrganizationsQuery(array $organizations): ORM\Query
    {
        $entityManager = $this->getEntityManager();

        $now = Utils\Time::now();

        $query = $entityManager->createQuery(<<<SQL
            SELECT c
            FROM App\Entity\Contract c

            WHERE c.startAt <= :now
            AND :now <= c.endAt
            AND c.organization IN (:organizations)

            ORDER BY c.name
        SQL);

        $query->setParameter('now', $now);
        $query->setParameter('organizations', $organizations);

        return $query;
    }

    /**
     * @return Contract[]
     */
    public function findOngoingByOrganization(Organization $organization): array
    {
        $query = $this->findOngoingByOrganizationsQuery([$organization]);
        return $query->getResult();
    }
}
