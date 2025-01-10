<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;
use Symfony\Bundle\SecurityBundle\Security;

class ActorsLister
{
    public const VALID_ROLE_TYPES = ['any', 'user', 'agent'];

    public function __construct(
        private Repository\OrganizationRepository $orgaRepository,
        private Repository\UserRepository $userRepository,
        private Sorter\UserSorter $userSorter,
        private Security $security,
    ) {
    }

    /**
     * Return the users with access to the given organization.
     *
     * The list can be restricted to a specific role type (user or agent).
     *
     * The list is ordered. If the logged-in user is part of the list, they are
     * placed at the beginning of the list.
     *
     * If the logged-in user doesn't have access to the organization, an empty
     * list is returned.
     *
     * @param value-of<self::VALID_ROLE_TYPES> $roleType
     *
     * @return Entity\User[]
     */
    public function findByOrganization(Entity\Organization $organization, string $roleType = 'any'): array
    {
        /** @var Entity\User */
        $currentUser = $this->security->getUser();

        if (!$this->orgaRepository->isAuthorizedInOrganization($currentUser, $organization)) {
            return [];
        }

        $users = $this->userRepository->findByAccessToOrganizations([$organization], $roleType);

        return $this->sortUsers($users);
    }

    /**
     * Return the users of the organizations to which the logged-in user has access.
     *
     * The list can be restricted to a specific role type (user or agent).
     *
     * The list is ordered. If the logged-in user is part of the list, they are
     * placed at the beginning of the list.
     *
     * @param value-of<self::VALID_ROLE_TYPES> $roleType
     *
     * @return Entity\User[]
     */
    public function findAll(string $roleType = 'any'): array
    {
        /** @var Entity\User */
        $currentUser = $this->security->getUser();

        $authorizedOrganizations = $this->orgaRepository->findAuthorizedOrganizations($currentUser);
        $users = $this->userRepository->findByAccessToOrganizations($authorizedOrganizations, $roleType);

        return $this->sortUsers($users);
    }

    /**
     * Sort a list of users and put the logged-in user to the beginning of the
     * list.
     *
     * @param Entity\User[] $users
     *
     * @return Entity\User[]
     */
    private function sortUsers(array $users): array
    {
        $this->userSorter->sort($users);

        // Make sure that the logged-in user is the first of the list.
        $currentUser = $this->security->getUser();
        $currentUserKey = array_search($currentUser, $users);
        if ($currentUserKey !== false) {
            $user = $users[$currentUserKey];
            unset($users[$currentUserKey]);
            $users = array_merge([$user], $users);
        }

        return $users;
    }
}
