<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Authorization;
use App\Entity\Organization;
use App\Entity\Role;
use App\Entity\User;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
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
            $query = $entityManager->createQuery(<<<SQL
                SELECT a, r
                FROM App\Entity\Authorization a
                INDEX BY a.organization
                JOIN a.role r
                WHERE a.holder = :user
                AND (a.organization = :organization OR a.organization IS NULL)
                AND (r.type = 'user' OR r.type = 'operational')
            SQL);
            $query->setParameter('user', $user);
            $query->setParameter('organization', $organization);

            $authorizationsIndexByOrgaIds = $query->getResult();
            if (count($authorizationsIndexByOrgaIds) === 0) {
                // no authorization? too bad
                return null;
            } elseif (count($authorizationsIndexByOrgaIds) === 1) {
                // There is only one authorization given for this organization.
                return array_pop($authorizationsIndexByOrgaIds);
            } else {
                // There are several authorizations given (i.e. a global and a
                // scoped), return only the scoped one as it's more specific.
                unset($authorizationsIndexByOrgaIds[null]);
                return array_pop($authorizationsIndexByOrgaIds);
            }
        } else {
            $query = $entityManager->createQuery(<<<SQL
                SELECT a, r
                FROM App\Entity\Authorization a
                INDEX BY a.organization
                JOIN a.role r
                WHERE a.holder = :user
                AND a.organization IS NULL
                AND (r.type = 'user' OR r.type = 'operational')
            SQL);
            $query->setParameter('user', $user);

            return $query->getOneOrNullResult();
        }
    }
}
