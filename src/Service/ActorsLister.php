<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
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
     * @param 'any'|'user'|'tech' $role
     *
     * @return User[]
     */
    public function findByOrganization(Organization $organization, string $role = 'any'): array
    {
        /** @var User */
        $currentUser = $this->security->getUser();

        $authorizedOrgas = $this->orgaRepository->findAuthorizedOrganizations($currentUser);
        $authorizedOrgaIds = array_map(fn ($orga) => $orga->getId(), $authorizedOrgas);

        $organizationIds = $organization->getParentOrganizationIds();
        $organizationIds[] = $organization->getId();

        $organizationIds = array_intersect($authorizedOrgaIds, $organizationIds);

        return $this->findByOrganizationIds($organizationIds, $role);
    }

    /**
     * @param 'any'|'user'|'tech' $role
     *
     * @return User[]
     */
    public function findAll(string $role = 'any'): array
    {
        /** @var User */
        $currentUser = $this->security->getUser();

        $authorizedOrgas = $this->orgaRepository->findAuthorizedOrganizations($currentUser);
        $authorizedOrgaIds = array_map(fn ($orga) => $orga->getId(), $authorizedOrgas);

        return $this->findByOrganizationIds($authorizedOrgaIds, $role);
    }

    /**
     * @param int[] $organizationIds
     * @param 'any'|'user'|'tech' $role
     *
     * @return User[]
     */
    private function findByOrganizationIds(array $organizationIds, string $role): array
    {
        $users = $this->userRepository->findByOrganizationIds($organizationIds, $role);

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
