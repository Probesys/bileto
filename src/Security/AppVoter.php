<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

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
            ($subject === null || $subject instanceof Organization) &&
            (str_starts_with($attribute, 'orga:') || str_starts_with($attribute, 'admin:'))
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // deny access if the user is not logged in
            return false;
        }

        if ($subject === null) {
            $authorization = $this->authorizationRepo->getAdminAuthorizationFor($user);
        } else {
            /** @var Organization $organization */
            $organization = $subject;
            $authorization = $this->authorizationRepo->getOrgaAuthorizationFor($user, $organization);
        }

        if (!$authorization) {
            return false;
        }

        return $authorization->getRole()->hasPermission($attribute);
    }
}
