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
        private Repository\SessionLogRepository $sessionLogRepository,
        private Repository\UserRepository $userRepository,
        private ActivityMonitor\ActiveUser $activeUser,
        private Security\Authorizer $authorizer,
        private Service\TeamService $teamService,
        private TranslatorInterface $translator,
    ) {
    }

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
