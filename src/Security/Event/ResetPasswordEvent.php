<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class ResetPasswordEvent extends Event
{
    public function __construct(
        private Request $request,
        private string $userIdentifier,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }
}
