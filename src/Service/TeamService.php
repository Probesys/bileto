<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use App\Repository\TeamRepository;

class TeamService
{
    public function __construct(
        private AuthorizationRepository $authorizationRepository,
        private TeamRepository $teamRepository,
    ) {
    }

    public function addAgent(Team $team, User $agent): void
    {
        if (!$team->hasAgent($agent)) {
            $team->addAgent($agent);
            $this->teamRepository->save($team, true);
            $this->authorizationRepository->grantToTeam($agent, $team);
        }
    }

    public function removeAgent(Team $team, User $agent): void
    {
        if ($team->hasAgent($agent)) {
            $team->removeAgent($agent);
            $this->teamRepository->save($team, true);
            $this->authorizationRepository->ungrantFromTeam($agent, $team);
        }
    }
}
