<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\UserSorter;
use Symfony\Bundle\SecurityBundle\Security;

class ActorsLister
{
    public function __construct(
        private AuthorizationRepository $authRepository,
        private OrganizationRepository $orgaRepository,
        private UserRepository $userRepository,
        private UserSorter $userSorter,
        private Security $security,
    ) {
    }

    /**
     * @return User[]
     */
    public function findAllForOrganization(Organization $organization): array
    {
        $currentUser = $this->security->getUser();

        $authorizedOrgaIds = $this->findAuthorizedOrganizationIds($currentUser);

        $orgaIds = $organization->getParentOrganizationIds();
        $orgaIds[] = $organization->getId();

        $orgaIds = array_intersect($authorizedOrgaIds, $orgaIds);

        $users = $this->userRepository->findByOrganizationIds($orgaIds);

        $this->userSorter->sort($users);

        $currentUserKey = array_search($currentUser, $users);
        if ($currentUserKey !== false) {
            // Make sure that the current user is first in the list
            $user = $users[$currentUserKey];
            unset($users[$currentUserKey]);
            return array_merge([$user], $users);
        } else {
            return $users;
        }
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        $currentUser = $this->security->getUser();

        $authorizedOrgaIds = $this->findAuthorizedOrganizationIds($currentUser);

        $users = $this->userRepository->findByOrganizationIds($authorizedOrgaIds);

        $this->userSorter->sort($users);

        $currentUserKey = array_search($currentUser, $users);
        if ($currentUserKey !== false) {
            // Make sure that the current user is first in the list
            $user = $users[$currentUserKey];
            unset($users[$currentUserKey]);
            return array_merge([$user], $users);
        } else {
            return $users;
        }
    }

    /**
     * @return int[]
     */
    private function findAuthorizedOrganizationIds(User $currentUser): array
    {
        $orgaIds = $this->authRepository->getAuthorizedOrganizationIds($currentUser);
        if (in_array(null, $orgaIds)) {
            $organizations = $this->orgaRepository->findAll();
        } else {
            $organizations = $this->orgaRepository->findWithSubOrganizations($orgaIds);
        }

        return array_map(function ($orga) {
            return $orga->getId();
        }, $organizations);
    }
}
