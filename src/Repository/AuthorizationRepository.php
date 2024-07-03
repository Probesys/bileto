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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @phpstan-type AuthorizationType 'orga'|'admin'
 * @phpstan-type Scope 'any'|Organization
 *
 * @extends ServiceEntityRepository<Authorization>
 */
class AuthorizationRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Authorization> */
    use CommonTrait;
    use UidGeneratorTrait;

    /** @var array<string, Authorization[]> */
    private array $cacheAuthorizations = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Authorization::class);
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
     * @param AuthorizationType $authorizationType
     * @param ?Scope $scope
     *
     * @return Authorization[]
     */
    public function getAuthorizations(string $authorizationType, User $user, mixed $scope): array
    {
        if ($authorizationType === 'orga' && $scope !== null) {
            return $this->getOrgaAuthorizationsFor($user, $scope);
        } elseif ($authorizationType === 'admin' && $scope === null) {
            return $this->getAdminAuthorizationsFor($user);
        } else {
            throw new \DomainException('Given authorization type and scope are not supported together');
        }
    }

    /**
     * @return Authorization[]
     */
    public function getAdminAuthorizationsFor(User $user): array
    {
        $authorizations = $this->loadUserAuthorizations($user);

        return array_filter($authorizations, function ($authorization): bool {
            $role = $authorization->getRole();
            $roleType = $role->getType();
            return $roleType === 'admin' || $roleType === 'super';
        });
    }

    /**
     * @param Organization|'any' $scope
     * @return Authorization[]
     */
    public function getOrgaAuthorizationsFor(User $user, mixed $scope): array
    {
        $authorizations = $this->loadUserAuthorizations($user);

        return array_filter($authorizations, function ($authorization) use ($scope): bool {
            $role = $authorization->getRole();
            $roleType = $role->getType();
            $correctType = $roleType === 'user' || $roleType === 'agent';

            if ($scope instanceof Organization) {
                $authOrganization = $authorization->getOrganization();
                $correctScope = (
                    $authOrganization === null ||
                    $authOrganization->getId() === $scope->getId()
                );
            } else {
                $correctScope = true;
            }

            return $correctType && $correctScope;
        });
    }

    /**
     * @return Authorization[]
     */
    public function loadUserAuthorizations(User $user): array
    {
        $keyCache = $user->getUid();

        if (!isset($this->cacheAuthorizations[$keyCache])) {
            $entityManager = $this->getEntityManager();

            $query = $entityManager->createQuery(<<<SQL
                SELECT a, r, o
                FROM App\Entity\Authorization a
                JOIN a.role r
                LEFT JOIN a.organization o
                WHERE a.holder = :user
            SQL);
            $query->setParameter('user', $user);

            $this->cacheAuthorizations[$keyCache] = $query->getResult();
        }

        return $this->cacheAuthorizations[$keyCache];
    }
}
