<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\TeamAuthorization;
use App\Entity\User;
use App\Repository;
use App\Security;

class TeamService
{
    public function __construct(
        private Repository\OrganizationRepository $organizationRepository,
        private Repository\TeamRepository $teamRepository,
        private Repository\TeamAuthorizationRepository $teamAuthorizationRepository,
        private Security\Authorizer $authorizer,
    ) {
    }

    public function addAgent(Team $team, User $agent): void
    {
        if (!$team->hasAgent($agent)) {
            $team->addAgent($agent);

            $this->teamRepository->save($team, true);

            $this->authorizer->grantToTeam($agent, $team);
        }
    }

    public function removeAgent(Team $team, User $agent): void
    {
        if ($team->hasAgent($agent)) {
            $team->removeAgent($agent);

            $this->teamRepository->save($team, true);

            $this->authorizer->ungrantFromTeam($agent, $team);
        }
    }

    public function createAuthorization(Team $team, Role $role, ?Organization $organization): void
    {
        $teamAuthorization = new TeamAuthorization();
        $teamAuthorization->setTeam($team);
        $teamAuthorization->setRole($role);
        $teamAuthorization->setOrganization($organization);

        $this->teamAuthorizationRepository->save($teamAuthorization, true);

        $this->authorizer->grantTeamAuthorization($team, $teamAuthorization);
    }

    public function removeAuthorization(TeamAuthorization $teamAuthorization): void
    {
        $this->teamAuthorizationRepository->remove($teamAuthorization, true);

        $team = $teamAuthorization->getTeam();
        $organizations = $this->organizationRepository->findObsoleteSupervisedOrganizations($team);

        foreach ($organizations as $organization) {
            $organization->setResponsibleTeam(null);
        }

        $this->organizationRepository->save($organizations, true);
    }
}
