<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;

class ActorCreator implements ResetInterface
{
    /** @var array<string, Entity\User> */
    private array $cache = [];

    public function __construct(
        private Repository\UserRepository $userRepository,
        private UserCreator $userCreator,
        private Security $security,
    ) {
    }

    public function findOrCreateByEmail(string $email, bool $flush = true): Entity\User
    {
        if (isset($this->cache[$email])) {
            // Make sure to not create the same users several times if the
            // email is present as both "requester" and "observer"
            return $this->cache[$email];
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if ($user) {
            $this->cache[$email] = $user;
            return $user;
        }

        /** @var ?Entity\User */
        $currentUser = $this->security->getUser();

        $user = $this->userCreator->create(
            $email,
            locale: $currentUser?->getLocale(),
            grantDefaultAuthorizations: false,
            flush: $flush,
        );

        $this->cache[$email] = $user;

        return $user;
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
