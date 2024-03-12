<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Authorization;
use App\Entity\Organization;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAuthorization;
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

    public function grantToTeam(User $user, Team $team): void
    {
        // Make sure to remove existing team authorizations of this user
        $this->ungrantFromTeam($user, $team);

        // Copy the team authorizations to the user.
        $teamAuthorizations = $team->getTeamAuthorizations();
        foreach ($teamAuthorizations as $teamAuthorization) {
            $authorization = Authorization::fromTeamAuthorization($teamAuthorization);
            $authorization->setHolder($user);

            $this->getEntityManager()->persist($authorization);
        }

        $this->getEntityManager()->flush();
    }

    public function ungrantFromTeam(User $user, Team $team): void
    {
        $teamAuthorizations = $team->getTeamAuthorizations();

        $authorizations = $this->findBy([
            'holder' => $user,
            'teamAuthorization' => $teamAuthorizations->toArray(),
        ]);

        foreach ($authorizations as $authorization) {
            $this->getEntityManager()->remove($authorization);
        }

        $this->getEntityManager()->flush();
    }

    public function grantTeamAuthorization(Team $team, TeamAuthorization $teamAuthorization): void
    {
        if ($team->getId() !== $teamAuthorization->getTeam()->getId()) {
            throw new \DomainException('Cannot grant this team authorization as it’s not attached to the given team.');
        }

        $users = $team->getAgents();
        foreach ($users as $user) {
            $authorization = Authorization::fromTeamAuthorization($teamAuthorization);
            $authorization->setHolder($user);

            $this->getEntityManager()->persist($authorization);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @return Authorization[]
     */
    public function getAdminAuthorizationsFor(User $user): array
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

        return $query->getResult();
    }

    /**
     * @param Organization|'any' $scope
     * @return Authorization[]
     */
    public function getOrgaAuthorizationsFor(User $user, mixed $scope): array
    {
        $entityManager = $this->getEntityManager();

        $queryBuilder = $entityManager->createQueryBuilder();
        $queryBuilder->select(['a', 'r']);
        $queryBuilder->from('App\Entity\Authorization', 'a');
        $queryBuilder->join('a.role', 'r');
        $queryBuilder->where('a.holder = :user');
        $queryBuilder->andWhere("r.type = 'user' OR r.type = 'agent'");

        $queryBuilder->setParameter('user', $user);

        if ($scope instanceof Organization) {
            $queryBuilder->andWhere('a.organization = :organization OR a.organization IS NULL');
            $queryBuilder->setParameter('organization', $scope);
        }

        $query = $queryBuilder->getQuery();
        return $query->getResult();
    }
}
