<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;
use App\Utils;

class UserService
{
    public function __construct(
        private Repository\OrganizationRepository $organizationRepository,
    ) {
    }

    public function getDefaultOrganization(Entity\User $user): ?Entity\Organization
    {
        $organization = $user->getOrganization();

        if ($organization) {
            return $organization;
        }

        $domain = Utils\Email::extractDomain($user->getEmail());
        $domainOrganization = $this->organizationRepository->findOneByDomainOrDefault($domain);

        if ($domainOrganization) {
            return $domainOrganization;
        }

        return null;
    }
}
