<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\User\UserInterface;

class UserToken extends AbstractToken
{
    public function __construct(UserInterface $user)
    {
        parent::__construct($user->getRoles());

        $this->setUser($user);
    }
}
