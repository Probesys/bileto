<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Repository\AuthorizationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Authorizer provides two methods to check permissions in the application.
 *
 * For more consistency within the application, please use this class instead
 * of Security to call `isGranted()`.
 *
 * @phpstan-import-type Scope from AuthorizationRepository
 */
class Authorizer
{
    public function __construct(
        private AuthorizationRepository $authorizationRepository,
        private Security $security,
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    /**
     * Check that the attribute is granted for the currently connected user.
     *
     * @see Security::isGranted
     */
    public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $this->security->isGranted($attribute, $subject);
    }

    /**
     * Check that the attribute is granted for the given user.
     */
    public function isGrantedToUser(UserInterface $user, mixed $attribute, mixed $subject = null): bool
    {
        $token = new Authentication\UserToken($user);
        return $this->accessDecisionManager->decide($token, [$attribute], $subject);
    }

    /**
     * @param Scope $scope
     */
    public function isAgent(mixed $scope): bool
    {
        /** @var ?\App\Entity\User */
        $user = $this->security->getUser();
        if (!$user) {
            return false;
        }

        $authorizations = $this->authorizationRepository->getAuthorizations('orga', $user, $scope);

        foreach ($authorizations as $authorization) {
            if ($authorization->getRole()->getType() === 'agent') {
                return true;
            }
        }

        return false;
    }
}
