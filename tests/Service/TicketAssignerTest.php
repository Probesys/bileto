<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests\Service;

use App\Service;
use App\Tests;
use App\Tests\Factory;
use App\Utils;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TicketAssignerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;
    use Tests\AuthorizationHelper;

    private Service\TicketAssigner $ticketAssigner;

    #[Before]
    public function setupTest(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        /** @var Service\TicketAssigner */
        $ticketAssigner = $container->get(Service\TicketAssigner::class);
        $this->ticketAssigner = $ticketAssigner;
    }

    public function testGetDefaultResponsibleTeamWithSeveralTeamsHavingAccessToOrganization(): void
    {
        $team1 = Factory\TeamFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'day'),
            'isResponsible' => true,
        ])->_real();
        $team2 = Factory\TeamFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'day'),
            'isResponsible' => true,
        ])->_real();
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantTeam($team1, ['orga:see'], $organization);
        $this->grantTeam($team2, ['orga:see'], $organization);

        $responsibleTeam = $this->ticketAssigner->getDefaultResponsibleTeam($organization);

        $this->assertNotNull($responsibleTeam);
        $this->assertSame($team1->getId(), $responsibleTeam->getId());
    }

    public function testGetDefaultResponsibleTeamWithNoTeamsHavingAccessToOrganization(): void
    {
        $team1 = Factory\TeamFactory::createOne([
            'isResponsible' => true,
        ])->_real();
        $team2 = Factory\TeamFactory::createOne([
            'isResponsible' => true,
        ])->_real();
        $organization = Factory\OrganizationFactory::createOne()->_real();

        $responsibleTeam = $this->ticketAssigner->getDefaultResponsibleTeam($organization);

        $this->assertNull($responsibleTeam);
    }

    public function testGetDefaultResponsibleTeamWithDeclaredResponsibleTeam(): void
    {
        $team1 = Factory\TeamFactory::createOne([
            'createdAt' => Utils\Time::ago(2, 'day'),
            'isResponsible' => true,
        ])->_real();
        $team2 = Factory\TeamFactory::createOne([
            'createdAt' => Utils\Time::ago(1, 'day'),
            'isResponsible' => true,
        ])->_real();
        $organization = Factory\OrganizationFactory::createOne([
            'responsibleTeam' => $team2,
        ])->_real();
        $this->grantTeam($team1, ['orga:see'], $organization);
        $this->grantTeam($team2, ['orga:see'], $organization);

        $responsibleTeam = $this->ticketAssigner->getDefaultResponsibleTeam($organization);

        $this->assertNotNull($responsibleTeam);
        $this->assertSame($team2->getId(), $responsibleTeam->getId());
    }

    public function testGetDefaultResponsibleTeamWithNoResponsibleTeams(): void
    {
        $team1 = Factory\TeamFactory::createOne([
            'isResponsible' => false,
        ])->_real();
        $team2 = Factory\TeamFactory::createOne([
            'isResponsible' => false,
        ])->_real();
        $organization = Factory\OrganizationFactory::createOne()->_real();
        $this->grantTeam($team1, ['orga:see'], $organization);
        $this->grantTeam($team2, ['orga:see'], $organization);

        $responsibleTeam = $this->ticketAssigner->getDefaultResponsibleTeam($organization);

        $this->assertNull($responsibleTeam);
    }
}
