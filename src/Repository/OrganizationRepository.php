<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 *
 * @method Organization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Organization|null findOneBy(array $criteria, array $orderBy = null)
 * @method Organization[]    findAll()
 * @method Organization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrganizationRepository extends ServiceEntityRepository
{
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function save(Organization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Organization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param int[] $organizationIds
     *
     * @return Organization[]
     */
    public function findWithSubOrganizations(array $organizationIds): array
    {
        $entityManager = $this->getEntityManager();
        $queryBuilder = $entityManager->createQueryBuilder();

        $queryBuilder->select('o');
        $queryBuilder->from('\App\Entity\Organization', 'o');
        $queryBuilder->where('o.id IN (:ids)');
        $queryBuilder->setParameter('ids', $organizationIds);

        foreach ($organizationIds as $key => $organizationId) {
            $expr = $queryBuilder->expr()->like(
                'o.parentsPath',
                "CONCAT('%/', :id{$key}, '/%')"
            );
            $queryBuilder->orWhere($expr);
            $queryBuilder->setParameter("id{$key}", $organizationId);
        }

        $query = $queryBuilder->getQuery();
        return $query->getResult();
    }
}
