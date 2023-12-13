<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\ActivityMonitor;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Return the user to be used in the monitoring operations (default is the
 * connected user, but it can be changed to a different value).
 */
class ActiveUser
{
    private static ?User $customActiveUser = null;

    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * Return the active user.
     */
    public function get(): ?User
    {
        if (self::$customActiveUser) {
            return self::$customActiveUser;
        } else {
            /** @var ?User $activeUser */
            $activeUser = $this->security->getUser();
            return $activeUser;
        }
    }

    /**
     * Change the active user.
     *
     * This should be used in non-HTTP contexts, such as CLI commands or in
     * workers operations. In a HTTP context, we want the connected user, so
     * you should not have to call this method.
     *
     * Pass `null` to reset the active user and use the connected user.
     */
    public function change(?User $user): void
    {
        self::$customActiveUser = $user;
    }
}
