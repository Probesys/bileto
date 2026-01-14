<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
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

    public function addAgent(Entity\Team $team, Entity\User $agent): void
    {
        if (!$team->hasAgent($agent)) {
            $team->addAgent($agent);

            $this->teamRepository->save($team, true);

            $this->authorizer->grantToTeam($agent, $team);
        }
    }

    public function removeAgent(Entity\Team $team, Entity\User $agent): void
    {
        if ($team->hasAgent($agent)) {
            $team->removeAgent($agent);

            $this->teamRepository->save($team, true);

            $this->authorizer->ungrantFromTeam($agent, $team);
        }
    }

    public function createAuthorization(Entity\TeamAuthorization $teamAuthorization): void
    {
        $this->teamAuthorizationRepository->save($teamAuthorization, true);

        $team = $teamAuthorization->getTeam();
        $this->authorizer->grantTeamAuthorization($team, $teamAuthorization);
    }

    public function removeAuthorization(Entity\TeamAuthorization $teamAuthorization): void
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
