<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, string|Organization|null>
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
                $subject === 'global' ||
                $subject === 'any'
            ))
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // deny access if the user is not logged in
            return false;
        }

        if (str_starts_with($attribute, 'orga:') && $subject === 'any') {
            $authorizations = $this->authorizationRepo->findBy([
                'holder' => $user,
            ]);

            foreach ($authorizations as $authorization) {
                if ($authorization->getRole()->hasPermission($attribute)) {
                    return true;
                }
            }

            return false;
        }

        if (str_starts_with($attribute, 'orga:') && $subject === 'global') {
            $authorization = $this->authorizationRepo->getOrgaAuthorizationFor($user, null);
        } elseif (str_starts_with($attribute, 'orga:') && $subject instanceof Organization) {
            /** @var Organization $organization */
            $organization = $subject;
            $authorization = $this->authorizationRepo->getOrgaAuthorizationFor($user, $organization);
        } elseif (str_starts_with($attribute, 'admin:')) {
            $authorization = $this->authorizationRepo->getAdminAuthorizationFor($user);
        } else {
            $authorization = null;
        }

        if (!$authorization) {
            return false;
        }

        return $authorization->getRole()->hasPermission($attribute);
    }
}
