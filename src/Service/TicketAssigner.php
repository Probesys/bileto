<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity;
use App\Repository;

class TicketAssigner
{
    public function __construct(
        private Repository\TeamRepository $teamRepository,
    ) {
    }

    public function getDefaultResponsibleTeam(Entity\Organization $organization): ?Entity\Team
    {
        $responsibleTeam = $organization->getResponsibleTeam();

        if ($responsibleTeam) {
            return $responsibleTeam;
        }

        $teams = $this->teamRepository->findByOrganization($organization);

        if (count($teams) === 0) {
            return null;
        }

        uasort($teams, function (Entity\Team $t1, Entity\Team $t2): int {
            return $t1->getCreatedAt() <=> $t2->getCreatedAt();
        });

        return $teams[0];
    }
}
