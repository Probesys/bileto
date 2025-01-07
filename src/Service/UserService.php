<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;
use App\Security;
use App\Utils;

class UserService
{
    public function __construct(
        private Repository\OrganizationRepository $organizationRepository,
        private Security\Authorizer $authorizer,
    ) {
    }

    public function getDefaultOrganization(Entity\User $user): ?Entity\Organization
    {
        $organization = $user->getOrganization();

        if (
            $organization &&
            $this->authorizer->isGrantedToUser($user, 'orga:create:tickets', $organization)
        ) {
            return $organization;
        }

        $domain = Utils\Email::extractDomain($user->getEmail());
        $domainOrganization = $this->organizationRepository->findOneByDomainOrDefault($domain);

        if (
            $domainOrganization &&
            $this->authorizer->isGrantedToUser($user, 'orga:create:tickets', $domainOrganization)
        ) {
            return $domainOrganization;
        }

        $authorizedOrganizations = $this->organizationRepository->findAuthorizedOrganizations(
            $user,
            roleType: 'user'
        );

        // Return the first organization in which the user can create tickets.
        return Utils\ArrayHelper::find(
            $authorizedOrganizations,
            function ($organization) use ($user): bool {
                return $this->authorizer->isGrantedToUser(
                    $user,
                    'orga:create:tickets',
                    $organization
                );
            }
        );
    }
}
