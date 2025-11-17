<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use App\Uid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @phpstan-type AuthorizationType 'orga'|'admin'
 * @phpstan-type Scope 'any'|Entity\Organization
 *
 * @extends ServiceEntityRepository<Entity\Authorization>
 */
class AuthorizationRepository extends ServiceEntityRepository implements Uid\UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Entity\Authorization> */
    use CommonTrait;
    use Uid\UidGeneratorTrait;

    /** @var array<string, array{int, Entity\Authorization[]}> */
    private array $cacheAuthorizations = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\Authorization::class);
    }

    /**
     * @param AuthorizationType $authorizationType
     * @param ?Scope $scope
     *
     * @return Entity\Authorization[]
     */
    public function getAuthorizations(string $authorizationType, Entity\User $user, mixed $scope): array
    {
        if ($authorizationType === 'orga' && $scope !== null) {
            return $this->getOrgaAuthorizations($user, $scope);
        } elseif ($authorizationType === 'admin' && $scope === null) {
            return $this->getAdminAuthorizations($user);
        } else {
            throw new \DomainException('Given authorization type and scope are not supported together');
        }
    }

    /**
     * Return the 'admin' authorizations of the user.
     *
     * @return Entity\Authorization[]
     */
    public function getAdminAuthorizations(Entity\User $user): array
    {
        $authorizations = $this->loadUserAuthorizations($user);

        return array_filter($authorizations, function (Entity\Authorization $authorization): bool {
            $role = $authorization->getRole();
            $roleType = $role->getType();
            return $roleType === 'admin' || $roleType === 'super';
        });
    }

    /**
     * Return the 'orga' authorizations of the user.
     *
     * The list of authorizations can be restricted to a specific organization
     * or to a specific role type.
     *
     * @param Scope $scope
     * @param 'any'|'user'|'agent' $roleType
     *
     * @return Entity\Authorization[]
     */
    public function getOrgaAuthorizations(Entity\User $user, mixed $scope = 'any', string $roleType = 'any'): array
    {
        $authorizations = $this->loadUserAuthorizations($user);

        return array_filter(
            $authorizations,
            function (Entity\Authorization $authorization) use ($scope, $roleType): bool {
                $role = $authorization->getRole();
                $authRoleType = $role->getType();
                $correctType = $authRoleType === 'user' || $authRoleType === 'agent';

                if ($roleType !== 'any') {
                    $correctType = $authRoleType === $roleType;
                }

                if ($scope instanceof Entity\Organization) {
                    $authOrganization = $authorization->getOrganization();
                    $correctScope = (
                        $authOrganization === null ||
                        $authOrganization->getId() === $scope->getId()
                    );
                } else {
                    $correctScope = true;
                }

                return $correctType && $correctScope;
            }
        );
    }

    /**
     * Return the list of user's authorizations.
     *
     * @return Entity\Authorization[]
     */
    public function loadUserAuthorizations(Entity\User $user): array
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
