<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity;
use App\Repository;
use App\Utils;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @phpstan-import-type Scope from Repository\AuthorizationRepository
 *
 * @extends Voter<string, Scope|Entity\Ticket|null>
 */
class AppVoter extends Voter
{
    public function __construct(
        private Repository\AuthorizationRepository $authorizationRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (
            (str_starts_with($attribute, 'admin:') && $subject === null) ||
            (str_starts_with($attribute, 'orga:') && (
                $subject instanceof Entity\Organization ||
                $subject instanceof Entity\Ticket ||
                $subject === 'any'
            ))
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!($user instanceof Entity\User) || $user->isAnonymized()) {
            // Deny access if the user is not set or if the user is anonymized.
            return false;
        }

        if (str_starts_with($attribute, 'orga:')) {
            $authorizationType = 'orga';
        } elseif (str_starts_with($attribute, 'admin:')) {
            $authorizationType = 'admin';
        } else {
            throw new \DomainException("Permission must start by 'orga:' or 'admin:' (got {$attribute})");
        }

        if ($subject instanceof Entity\Ticket) {
            $scope = $subject->getOrganization();
            $ticket = $subject;
        } else {
            $scope = $subject;
            $ticket = null;
        }

        $authorizations = $this->authorizationRepository->getAuthorizations(
            $authorizationType,
            $user,
            $scope,
        );

        $isGranted = Utils\ArrayHelper::any($authorizations, function ($authorization) use ($attribute): bool {
            return $authorization->getRole()->hasPermission($attribute);
        });

        if (!$isGranted) {
            return false;
        } elseif (!$ticket || $ticket->hasActor($user)) {
            return true;
        } else {
            return $this->voteOnAttribute('orga:see:tickets:all', $scope, $token);
        }
    }
}
