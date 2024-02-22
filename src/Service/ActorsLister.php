<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\Sorter\UserSorter;
use Symfony\Bundle\SecurityBundle\Security;

class ActorsLister
{
    public function __construct(
        private OrganizationRepository $orgaRepository,
        private UserRepository $userRepository,
        private UserSorter $userSorter,
        private Security $security,
    ) {
    }

    /**
     * @param 'any'|'user'|'agent' $roleType
     *
     * @return User[]
     */
    public function findByOrganization(Organization $organization, string $roleType = 'any'): array
    {
        /** @var User */
        $currentUser = $this->security->getUser();

        $authorizedOrgas = $this->orgaRepository->findAuthorizedOrganizations($currentUser);
        $authorizedOrgaIds = array_map(fn ($orga): int => $orga->getId(), $authorizedOrgas);

        if (!in_array($organization->getId(), $authorizedOrgaIds)) {
            return [];
        }

        return $this->findByOrganizationIds([$organization->getId()], $roleType);
    }

    /**
     * @param 'any'|'user'|'agent' $roleType
     *
     * @return User[]
     */
    public function findAll(string $roleType = 'any'): array
    {
        /** @var User */
        $currentUser = $this->security->getUser();

        $authorizedOrgas = $this->orgaRepository->findAuthorizedOrganizations($currentUser);
        $authorizedOrgaIds = array_map(fn ($orga): int => $orga->getId(), $authorizedOrgas);

        return $this->findByOrganizationIds($authorizedOrgaIds, $roleType);
    }

    /**
     * @param int[] $organizationIds
     * @param 'any'|'user'|'agent' $roleType
     *
     * @return User[]
     */
    private function findByOrganizationIds(array $organizationIds, string $roleType): array
    {
        $users = $this->userRepository->findByOrganizationIds($organizationIds, $roleType);

        $this->userSorter->sort($users);

        // Make sure that the current user is the first of the list
        $currentUser = $this->security->getUser();
        $currentUserKey = array_search($currentUser, $users);
        if ($currentUserKey !== false) {
            $user = $users[$currentUserKey];
            unset($users[$currentUserKey]);
            return array_merge([$user], $users);
        } else {
            return $users;
        }
    }
}
