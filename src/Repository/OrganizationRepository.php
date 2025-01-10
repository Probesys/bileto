<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use App\Uid;
use App\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entity\Organization>
 */
class OrganizationRepository extends ServiceEntityRepository implements Uid\UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Entity\Organization> */
    use CommonTrait;
    use Uid\UidGeneratorTrait;
    /** @phpstan-use FindOrCreateTrait<Entity\Organization> */
    use FindOrCreateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\Organization::class);
    }

    /**
     * @return Entity\Organization[]
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
     * Return the list of organizations that the user has access to.
     *
     * The list can be restricted to a specific role type. It means that if
     * user has access to an organization as a "user", it will not be returned
     * if role type is set to "agent".
     *
     * @param 'any'|'user'|'agent' $roleType
     *
     * @return Entity\Organization[]
     */
    public function findAuthorizedOrganizations(Entity\User $user, string $roleType = 'any'): array
    {
        $entityManager = $this->getEntityManager();
        /** @var AuthorizationRepository */
        $authorizationRepository = $entityManager->getRepository(Entity\Authorization::class);

        $authorizations = $authorizationRepository->getOrgaAuthorizations($user, roleType: $roleType);

        $scopedOrganizations = array_map(function ($authorization): ?Entity\Organization {
            return $authorization->getOrganization();
        }, $authorizations);

        if (in_array(null, $scopedOrganizations)) {
            // If "null" is in the list of the scoped organizations, it means
            // that an authorization is applied globally and grants a global
            // access to the user. We can return all the organizations then.
            return $this->findAll();
        } else {
            return $scopedOrganizations;
        }
    }

    /**
     * @return Entity\Organization[]
     */
    public function findObsoleteSupervisedOrganizations(Entity\Team $team): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT o
            FROM App\Entity\Organization o
            JOIN o.responsibleTeam t

            WHERE t = :team
            AND NOT EXISTS (
                SELECT 1
                FROM App\Entity\TeamAuthorization ta
                WHERE ta.team = :team
                AND (
                    ta.organization IS NULL
                    OR ta.organization = o
                )
            )
        SQL);

        $query->setParameter('team', $team);

        return $query->getResult();
    }

    public function findOneByDomain(string $domain): ?Entity\Organization
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

    public function findOneByDomainOrDefault(string $domain): ?Entity\Organization
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
