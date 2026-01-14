<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Security\Authorizer;
use Twig\Attribute\AsTwigFunction;

/**
 * @phpstan-import-type Scope from \App\Repository\AuthorizationRepository
 */
class SecurityExtension
{
    public function __construct(
        private Authorizer $authorizer,
    ) {
    }

    /**
     * @param Scope $scope
     */
    #[AsTwigFunction('is_agent')]
    public function isAgent(mixed $scope): bool
    {
        return $this->authorizer->isAgent($scope);
    }
}
