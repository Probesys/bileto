<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Security;

use App\Entity;
use App\Repository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Authorizer provides two methods to check permissions in the application.
 *
 * For more consistency within the application, please use this class instead
 * of Security to call `isGranted()`.
 *
 * @phpstan-import-type Scope from Repository\AuthorizationRepository
 */
class Authorizer
{
    public function __construct(
        private Repository\AuthorizationRepository $authorizationRepository,
        private Repository\UserRepository $userRepository,
        private Security $security,
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    public function grant(
        Entity\User $user,
        Entity\Role $role,
        ?Entity\Organization $organization = null,
        bool $flush = true
    ): void {
        $authorization = new Entity\Authorization();
        $authorization->setRole($role);
        $authorization->setOrganization($organization);

        $user->addAuthorization($authorization);

        $this->authorizationRepository->save($authorization, $flush);
    }

    public function ungrant(Entity\User $user, Entity\Authorization $authorization): void
    {
        if ($user->getId() !== $authorization->getHolder()->getId()) {
            throw new \DomainException('Cannot ungrant this authorization as it’s not attached to the given user.');
        }

        $this->authorizationRepository->remove($authorization, true);
        $this->refreshDefaultOrganization($user);
    }

    public function grantToTeam(Entity\User $user, Entity\Team $team): void
    {
        // Make sure to remove existing team authorizations of this user
        $this->ungrantFromTeam($user, $team);

        // Copy the team authorizations to the user.
        $teamAuthorizations = $team->getTeamAuthorizations();
        $authorizations = [];
        foreach ($teamAuthorizations as $teamAuthorization) {
            $authorization = Entity\Authorization::fromTeamAuthorization($teamAuthorization);
            $authorization->setHolder($user);

            $authorizations[] = $authorization;
        }

        $this->authorizationRepository->save($authorizations, true);
    }

    public function ungrantFromTeam(Entity\User $user, Entity\Team $team): void
    {
        $teamAuthorizations = $team->getTeamAuthorizations();

        $authorizations = $this->authorizationRepository->findBy([
            'holder' => $user,
            'teamAuthorization' => $teamAuthorizations->toArray(),
        ]);

        $this->authorizationRepository->remove($authorizations, true);
        $this->refreshDefaultOrganization($user);
    }

    public function grantTeamAuthorization(Entity\Team $team, Entity\TeamAuthorization $teamAuthorization): void
    {
        if ($team->getId() !== $teamAuthorization->getTeam()->getId()) {
            throw new \DomainException('Cannot grant this team authorization as it’s not attached to the given team.');
        }

        $users = $team->getAgents();
        $authorizations = [];

        foreach ($users as $user) {
            $authorization = Entity\Authorization::fromTeamAuthorization($teamAuthorization);
            $authorization->setHolder($user);
            $authorizations[] = $authorization;
        }

        $this->authorizationRepository->save($authorizations, true);
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

    /**
     * Make sure that the default user's organization (if any) is still
     * accessible to them and remove it if it's not.
     *
     * This method must be used after ungranting an authorization of a user.
     */
    private function refreshDefaultOrganization(Entity\User $user): void
    {
        $defaultOrganization = $user->getOrganization();

        if (
            $defaultOrganization &&
            !$this->isGrantedToUser($user, 'orga:create:tickets', $defaultOrganization)
        ) {
            $user->setOrganization(null);
            $this->userRepository->save($user, true);
        }
    }
}
