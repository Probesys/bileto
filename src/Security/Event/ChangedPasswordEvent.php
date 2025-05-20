<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ChangedPasswordEvent extends Event
{
    public function __construct(
        private Request $request,
        private UserInterface $user,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }
}
