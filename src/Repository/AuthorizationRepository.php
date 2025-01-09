<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
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

    /** @var array<string, array{int, Authorization[]}> */
    private array $cacheAuthorizations = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Authorization::class);
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
     * Return the list of user's authorizations.
     *
     * @return Authorization[]
     */
    public function loadUserAuthorizations(User $user): array
    {
        if ($user->getId() === null) {
            return [];
        }

        // Remove the entries older than 10 seconds from the cache.
        foreach ($this->cacheAuthorizations as $key => $cache) {
            $timestamp = $cache[0];

            if ($timestamp < time() - 10) {
                unset($this->cacheAuthorizations[$key]);
            }
        }

        $keyCache = $user->getUid();

        if (!isset($this->cacheAuthorizations[$keyCache])) {
            // Load the authorizations of the user from the database only if
            // they are not in the cache yet.

            $entityManager = $this->getEntityManager();

            $query = $entityManager->createQuery(<<<SQL
                SELECT a, r, o
                FROM App\Entity\Authorization a
                JOIN a.role r
                LEFT JOIN a.organization o
                WHERE a.holder = :user
            SQL);
            $query->setParameter('user', $user);

            // The authorizations are now in the cache for the next 10 seconds.
            $this->cacheAuthorizations[$keyCache] = [time(), $query->getResult()];
        }

        return $this->cacheAuthorizations[$keyCache][1];
    }
}
