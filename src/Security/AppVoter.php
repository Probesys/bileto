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
 * @phpstan-import-type Scope from AuthorizationRepository
 *
 * @extends Voter<string, ?Scope>
 */
class AppVoter extends Voter
{
    private AuthorizationRepository $authorizationRepo;

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

        $authorizations = $this->authorizationRepo->getAuthorizations($authorizationType, $user, $scope);

        foreach ($authorizations as $authorization) {
            if ($authorization->getRole()->hasPermission($attribute)) {
                return true;
            }
        }

        return false;
    }
}
