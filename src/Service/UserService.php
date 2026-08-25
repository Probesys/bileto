<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\ActivityMonitor;
use App\Entity;
use App\Repository;
use App\Security;
use App\Service;
use App\Utils;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserService
{
    public function __construct(
        private Repository\EntityEventRepository $entityEventRepository,
        private Repository\OrganizationRepository $organizationRepository,
        private Repository\RoleRepository $roleRepository,
        private Repository\SessionLogRepository $sessionLogRepository,
        private Repository\UserRepository $userRepository,
        private ActivityMonitor\ActiveUser $activeUser,
        private Security\Authorizer $authorizer,
        private Service\TeamService $teamService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Return the default organization in which the user can create tickets.
     *
     * It defaults to the organization set on the user, then to the
     * organization corresponding to his email's domain (if any), then finally,
     * it looks for the first organization in which the user can create tickets.
     *
     * This method requires that the user has at least one authorization.
     * Otherwise, it returns null.
     */
    public function getDefaultOrganization(Entity\User $user): ?Entity\Organization
    {
        $organization = $user->getOrganization();

        if (
            $organization &&
            $this->authorizer->isGrantedForUser($user, 'orga:create:tickets', $organization)
        ) {
            return $organization;
        }

        $domain = Utils\Email::extractDomain($user->getEmail());
        $domainOrganization = $this->organizationRepository->findOneByDomainOrDefault($domain);

        if (
            $domainOrganization &&
            $this->authorizer->isGrantedForUser($user, 'orga:create:tickets', $domainOrganization)
        ) {
            return $domainOrganization;
        }

        $authorizedOrganizations = $this->organizationRepository->findAuthorizedOrganizations(
            $user,
            roleType: 'user'
        );

        // Return the first organization in which the user can create tickets.
        return Utils\ArrayHelper::find(
            $authorizedOrganizations,
            function ($organization) use ($user): bool {
                return $this->authorizer->isGrantedForUser(
                    $user,
                    'orga:create:tickets',
                    $organization
                );
            }
        );
    }

    /**
     * Grant a default authorization (default role on the user's default
     * organization if any).
     *
     * The authorization is not granted if the user already has at least one
     * authorization.
     */
    public function grantDefaultAuthorization(Entity\User $user, bool $flush = true): bool
    {
        if (!$user->getAuthorizations()->isEmpty()) {
            return false;
        }


        $defaultRole = $this->roleRepository->findDefault();

        if (!$defaultRole) {
            return false;
        }

        $defaultOrganization = $user->getOrganization();

        if (!$defaultOrganization) {
            $domain = Utils\Email::extractDomain($user->getEmail());
            $defaultOrganization = $this->organizationRepository->findOneByDomainOrDefault($domain);
        }

        if (!$defaultOrganization) {
            return false;
        }

        $this->authorizer->grant(
            $user,
            $defaultRole,
            $defaultOrganization,
            $flush,
        );

        return true;
    }

    public function anonymize(Entity\User $user): void
    {
        foreach ($user->getTeams() as $team) {
            $this->teamService->removeAgent($team, $user);
        }

        foreach ($user->getAuthorizations() as $authorization) {
            $this->authorizer->ungrant($user, $authorization);
        }

        $this->entityEventRepository->removeByEntity($user);
        $this->sessionLogRepository->removeByIdentifier($user->getUserIdentifier());

        $currentUser = $this->activeUser->get();
        $name = $this->translator->trans('users.anonymous', locale: $user->getLocale());
        $user->anonymize($name, by: $currentUser);
        $this->userRepository->save($user, flush: true);
    }
}
