<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Team;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Team> */
    use CommonTrait;
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /**
     * @return Team[]
     */
    public function findByOrganization(Organization $organization): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT t
            FROM App\Entity\Team t
            JOIN t.teamAuthorizations ta
            WHERE (ta.organization = :organization OR ta.organization IS NULL)
        SQL);

        $query->setParameter('organization', $organization);

        return $query->getResult();
    }

    /**
     * @return Team[]
     */
    public function findLike(string $value): array
    {
        $entityManager = $this->getEntityManager();

        $value = mb_strtolower($value);

        $query = $entityManager->createQuery(<<<SQL
            SELECT t
            FROM App\Entity\Team t
            WHERE LOWER(t.name) LIKE :value
        SQL);
        $query->setParameter('value', "%{$value}%");

        return $query->getResult();
    }
}
