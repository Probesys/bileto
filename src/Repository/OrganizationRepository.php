<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 *
 * @method Organization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Organization|null findOneBy(array $criteria, array $orderBy = null)
 * @method Organization[]    findAll()
 * @method Organization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @method Organization findOneOrCreateBy(array $criteria, array $valuesToCreate = [], bool $flush = false)
 */
class OrganizationRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;
    /** @phpstan-use FindOrCreateTrait<Organization> */
    use FindOrCreateTrait;

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
     * @return Organization[]
     */
    public function findLike(string $value): array
    {
        $entityManager = $this->getEntityManager();

        $value = mb_strtolower($value);

        $query = $entityManager->createQuery(<<<SQL
            SELECT o
            FROM App\Entity\Organization o
            WHERE LOWER(o.name) LIKE :value
        SQL);
        $query->setParameter('value', "%{$value}%");

        return $query->getResult();
    }

    /**
     * @return Organization[]
     */
    public function findAuthorizedOrganizations(User $user): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT IDENTITY(a.organization)
            FROM App\Entity\Authorization a
            JOIN a.role r
            WHERE a.holder = :user
            AND (r.type = 'user' OR r.type = 'agent')
        SQL);
        $query->setParameter('user', $user);

        $authorizedOrgaIds = $query->getSingleColumnResult();

        if (in_array(null, $authorizedOrgaIds)) {
            // If "null" is returned, it means that an authorization is applied globally.
            return $this->findAll();
        } else {
            return $this->findBy([
                'id' => $authorizedOrgaIds,
            ]);
        }
    }
}
