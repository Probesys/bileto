<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Authorization;
use App\Entity\Organization;
use App\Entity\Team;
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
     * @param 'any'|'user'|'agent' $roleType
     *
     * @return Organization[]
     */
    public function findAuthorizedOrganizations(User $user, string $roleType = 'any'): array
    {
        $entityManager = $this->getEntityManager();
        /** @var AuthorizationRepository */
        $authorizationRepository = $entityManager->getRepository(Authorization::class);
        $authorizations = $authorizationRepository->getOrgaAuthorizationsFor($user, scope: 'any');

        $authorizations = array_filter(
            $authorizations,
            function ($authorization) use ($roleType): bool {
                return $roleType === 'any' || $authorization->getRole()->getType() === $roleType;
            }
        );

        $authorizedOrgaIds = array_map(function ($authorization): ?int {
            $organization = $authorization->getOrganization();

            if ($organization) {
                return $organization->getId();
            } else {
                return null;
            }
        }, $authorizations);

        if (in_array(null, $authorizedOrgaIds)) {
            // If "null" is returned, it means that an authorization is applied globally.
            return $this->findAll();
        } else {
            return $this->findBy([
                'id' => $authorizedOrgaIds,
            ]);
        }
    }

    /**
     * @return Organization[]
     */
    public function findObsoleteSupervisedOrganizations(Team $team): array
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
