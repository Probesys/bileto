<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserSorter;
use Symfony\Bundle\SecurityBundle\Security;

class ActorsLister
{
    public function __construct(
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

        $orgaIds = $organization->getParentOrganizationIds();
        $orgaIds[] = $organization->getId();

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

        $users = $this->userRepository->findAll();

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
}
