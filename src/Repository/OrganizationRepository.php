<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Organization;
use App\Service\OrganizationSorter;
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

    private OrganizationSorter $organizationSorter;

    public function __construct(ManagerRegistry $registry, OrganizationSorter $organizationSorter)
    {
        parent::__construct($registry, Organization::class);

        $this->organizationSorter = $organizationSorter;
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
     * @return Organization[]
     */
    public function findAllAsTree(): array
    {
        $entityManager = $this->getEntityManager();
        $rootOrganizations = [];

        $query = $entityManager->createQuery(<<<SQL
            SELECT o
            FROM App\Entity\Organization o
            INDEX BY o.id
            SQL);

        $organizationsIndexByIds = $query->getResult();
        $this->organizationSorter->sort($organizationsIndexByIds);

        foreach ($organizationsIndexByIds as $organization) {
            $parentId = $organization->getParentOrganizationId();
            $parent = $organizationsIndexByIds[$parentId] ?? null;
            if ($parentId === null) {
                $rootOrganizations[] = $organization;
            } elseif ($parent) {
                $parent->addSubOrganization($organization);
            }
        }

        return $rootOrganizations;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneByAsTree(array $criteria, int $maxDepth = 0): Organization
    {
        $entityManager = $this->getEntityManager();

        $rootOrganization = $this->findOneBy($criteria);

        $query = $entityManager->createQuery(<<<SQL
            SELECT o
            FROM App\Entity\Organization o
            INDEX BY o.id
            WHERE o.parentsPath LIKE CONCAT('%/', :id, '/%')
            SQL);
        $query->setParameter('id', $rootOrganization->getId());

        $subOrganizationsIndexByIds = $query->getResult();
        $this->organizationSorter->sort($subOrganizationsIndexByIds);

        foreach ($subOrganizationsIndexByIds as $organization) {
            $orgaDepth = $organization->getDepth();
            if ($maxDepth > 0 && $orgaDepth > $maxDepth) {
                continue;
            }

            $parentId = $organization->getParentOrganizationId();
            $parent = $subOrganizationsIndexByIds[$parentId] ?? null;
            if ($parentId == $rootOrganization->getId()) {
                $rootOrganization->addSubOrganization($organization);
            } elseif ($parent) {
                $parent->addSubOrganization($organization);
            }
        }

        return $rootOrganization;
    }
}
