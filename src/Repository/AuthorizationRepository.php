<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Authorization;
use App\Entity\Organization;
use App\Entity\Role;
use App\Entity\User;
use App\Utils\Time;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Authorization>
 *
 * @method Authorization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Authorization|null findOneBy(array $criteria, array $orderBy = null)
 * @method Authorization[]    findAll()
 * @method Authorization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AuthorizationRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Authorization::class);
    }

    public function save(Authorization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Authorization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function grant(User $user, Role $role, ?Organization $organization = null): void
    {
        $authorization = new Authorization();
        $authorization->setHolder($user);
        $authorization->setRole($role);
        $authorization->setOrganization($organization);
        $this->save($authorization, true);
    }

    public function getAdminAuthorizationFor(User $user): ?Authorization
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT a, r
            FROM App\Entity\Authorization a
            JOIN a.role r
            WHERE a.holder = :user
            AND (r.type = 'admin' OR r.type = 'super')
        SQL);
        $query->setParameter('user', $user);

        return $query->getOneOrNullResult();
    }

    public function getOrgaAuthorizationFor(User $user, ?Organization $organization): ?Authorization
    {
        $entityManager = $this->getEntityManager();

        if ($organization) {
            // Build an array of organizations ids sorted by the most to the less
            // specific organizations (i.e. sub-orga first, root orga last)
            $orgaIds = $organization->getParentOrganizationIds();
            $orgaIds[] = $organization->getId();
            $orgaIds = array_reverse($orgaIds);

            $query = $entityManager->createQuery(<<<SQL
                SELECT a, r
                FROM App\Entity\Authorization a
                INDEX BY a.organization
                JOIN a.role r
                WHERE a.holder = :user
                AND (a.organization IN (:orgaIds) OR a.organization IS NULL)
                AND (r.type = 'orga:user' OR r.type = 'orga:tech')
            SQL);
            $query->setParameter('user', $user);
            $query->setParameter('orgaIds', $orgaIds);

            $authorizationsIndexByOrgaIds = $query->getResult();
            if (empty($authorizationsIndexByOrgaIds)) {
                // no authorization? too bad
                return null;
            }

            // Make sure to return the most specific authorization (remember that
            // orgaIds is already sorted from the most to the less specific).
            foreach ($orgaIds as $orgaId) {
                if (isset($authorizationsIndexByOrgaIds[$orgaId])) {
                    return $authorizationsIndexByOrgaIds[$orgaId];
                }
            }

            // The only possible remaining authorization is the global one (i.e.
            // not associated to a specific organization).
            return array_pop($authorizationsIndexByOrgaIds);
        } else {
            $query = $entityManager->createQuery(<<<SQL
                SELECT a, r
                FROM App\Entity\Authorization a
                INDEX BY a.organization
                JOIN a.role r
                WHERE a.holder = :user
                AND a.organization IS NULL
                AND (r.type = 'orga:user' OR r.type = 'orga:tech')
            SQL);
            $query->setParameter('user', $user);

            return $query->getOneOrNullResult();
        }
    }

    /**
     * @return int[]
     */
    public function getAuthorizedOrganizationIds(User $user): array
    {
        $entityManager = $this->getEntityManager();
        $queryBuilder = $entityManager->createQueryBuilder();

        $query = $entityManager->createQuery(<<<SQL
            SELECT IDENTITY(a.organization)
            FROM App\Entity\Authorization a
            JOIN a.role r
            WHERE a.holder = :user
            AND (r.type = 'orga:user' OR r.type = 'orga:tech')
        SQL);
        $query->setParameter('user', $user);

        return $query->getSingleColumnResult();
    }
}
