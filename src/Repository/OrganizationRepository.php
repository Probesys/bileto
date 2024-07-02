<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use App\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Organization> */
    use CommonTrait;
    use UidGeneratorTrait;
    /** @phpstan-use FindOrCreateTrait<Organization> */
    use FindOrCreateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
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

    public function findOneByDomain(string $domain): ?Organization
    {
        $domain = Utils\Url::sanitizeDomain($domain);

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT o
            FROM App\Entity\Organization o
            WHERE JSON_CONTAINS(o.domains, :domain) = true
        SQL);
        $query->setParameter('domain', '"' . $domain . '"');

        return $query->getOneOrNullResult();
    }

    public function findOneByDomainOrDefault(string $domain): ?Organization
    {
        $organization = $this->findOneByDomain($domain);

        if ($organization) {
            return $organization;
        }

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT o
            FROM App\Entity\Organization o
            WHERE JSON_CONTAINS(o.domains, '"*"') = true
        SQL);

        return $query->getOneOrNullResult();
    }
}
