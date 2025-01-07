<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Twig;

use App\Security\Authorizer;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @phpstan-import-type Scope from \App\Repository\AuthorizationRepository
 */
class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private Authorizer $authorizer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'is_granted_to_user',
                [$this, 'isGrantedToUser'],
            ),
            new TwigFunction(
                'is_agent',
                [$this, 'isAgent'],
            ),
        ];
    }

    public function isGrantedToUser(UserInterface $user, mixed $attribute, mixed $subject = null): bool
    {
        return $this->authorizer->isGrantedToUser($user, $attribute, $subject);
    }

    /**
     * @param Scope $scope
     */
    public function isAgent(mixed $scope): bool
    {
        return $this->authorizer->isAgent($scope);
    }
}
