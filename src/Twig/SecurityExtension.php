<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Security\Authorizer;
use Symfony\Component\Security\Core\User\UserInterface;
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

    #[AsTwigFunction('is_granted_to_user')]
    public function isGrantedToUser(UserInterface $user, mixed $attribute, mixed $subject = null): bool
    {
        return $this->authorizer->isGrantedToUser($user, $attribute, $subject);
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
