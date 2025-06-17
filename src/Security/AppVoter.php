<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity;
use App\Repository;
use App\Utils;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
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

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof Entity\User) {
            $vote?->addReason('User is not logged in');
            return false;
        }

        if ($user->isAnonymized()) {
            $vote?->addReason("User (id: {$user->getId()}) is anonymized");
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
            $reason = "Permission {$attribute} is not granted to user (id: {$user->getId()})";
            if ($scope instanceof Entity\Organization) {
                $reason .= " in the organization {$scope->getId()}";
            }
            $vote?->addReason($reason);
            return false;
        } elseif (!$ticket || $ticket->hasActor($user)) {
            return true;
        } else {
            return $this->voteOnAttribute('orga:see:tickets:all', $scope, $token, $vote);
        }
    }
}
