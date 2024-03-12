<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity\Authorization;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @phpstan-type AuthorizationType 'orga'|'admin'
 * @phpstan-type Scope 'any'|Organization
 *
 * @extends Voter<string, ?Scope>
 */
class AppVoter extends Voter
{
    private AuthorizationRepository $authorizationRepo;

    /** @var array<string, Authorization[]> */
    private array $cacheAuthorizations = [];

    public function __construct(AuthorizationRepository $authorizationRepo)
    {
        $this->authorizationRepo = $authorizationRepo;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (
            (str_starts_with($attribute, 'admin:') && $subject === null) ||
            (str_starts_with($attribute, 'orga:') && (
                $subject instanceof Organization ||
                $subject === 'any'
            ))
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $scope, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // deny access if the user is not logged in
            return false;
        }

        if (str_starts_with($attribute, 'orga:')) {
            $authorizationType = 'orga';
        } elseif (str_starts_with($attribute, 'admin:')) {
            $authorizationType = 'admin';
        } else {
            throw new \DomainException("Permission must start by 'orga:' or 'admin:' (got {$attribute})");
        }

        $authorizations = $this->getAuthorizations($authorizationType, $user, $scope);

        foreach ($authorizations as $authorization) {
            if ($authorization->getRole()->hasPermission($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param AuthorizationType $authorizationType
     * @param ?Scope $scope
     *
     * @return Authorization[]
     */
    private function getAuthorizations(string $authorizationType, User $user, mixed $scope): array
    {
        $cacheKey = self::getCacheKey($authorizationType, $user, $scope);
        if (isset($this->cacheAuthorizations[$cacheKey])) {
            return $this->cacheAuthorizations[$cacheKey];
        }

        if ($authorizationType === 'orga' && $scope !== null) {
            $authorizations = $this->authorizationRepo->getOrgaAuthorizationsFor($user, $scope);
            $this->cacheAuthorizations[$cacheKey] = $authorizations;
        } elseif ($authorizationType === 'admin' && $scope === null) {
            $authorizations = $this->authorizationRepo->getAdminAuthorizationsFor($user);
            $this->cacheAuthorizations[$cacheKey] = $authorizations;
        } else {
            throw new \DomainException('Given authorization type and scope are not supported together');
        }

        return $authorizations;
    }

    /**
     * @param AuthorizationType $authorizationType
     * @param ?Scope $scope
     */
    private static function getCacheKey(string $authorizationType, User $user, mixed $scope): string
    {
        $baseKey = "{$authorizationType}.{$user->getId()}";

        if ($scope === 'any') {
            $baseKey .= '.any';
        } elseif ($scope instanceof Organization) {
            $baseKey .= ".{$scope->getId()}";
        } elseif ($scope === null) {
            $baseKey .= '.null';
        } else {
            throw new \DomainException('The given scope is not supported');
        }

        return hash('sha256', $baseKey);
    }
}
