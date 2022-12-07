<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Security;

class ActorsLister
{
    private UserRepository $userRepository;

    private Security $security;

    public function __construct(UserRepository $userRepository, Security $security)
    {
        $this->userRepository = $userRepository;
        $this->security = $security;
    }

    /**
     * @return User[]
     */
    public function listUsers(): array
    {
        $currentUser = $this->security->getUser();
        $users = $this->userRepository->findBy([], ['email' => 'ASC']);

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
