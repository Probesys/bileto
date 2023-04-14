<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine;

use App\Entity\Organization;
use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\TicketRepository;
use Symfony\Bundle\SecurityBundle\Security;

class TicketSearcher
{
    public const QUERY_DEFAULT = 'status:open';
    public const QUERY_UNASSIGNED = 'status:open no:assignee';
    public const QUERY_OWNED = 'status:open involves:@me';

    private TicketRepository $ticketRepository;

    /** @var array<string,int[]> $orgaFilters */
    private array $orgaFilters = [
        'all' => [],
        'actor' => [],
    ];

    private Security $security;

    public function __construct(TicketRepository $ticketRepository, Security $security)
    {
        $this->ticketRepository = $ticketRepository;
        $this->security = $security;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->orgaFilters['all'] = [];
        $this->orgaFilters['actor'] = [];

        $this->addOrgaFilter($organization);

        return $this;
    }

    /**
     * @param Organization[] $organizations
     */
    public function setOrganizations(array $organizations): self
    {
        $this->orgaFilters['all'] = [];
        $this->orgaFilters['actor'] = [];

        foreach ($organizations as $organization) {
            $this->addOrgaFilter($organization);
        }

        return $this;
    }

    private function addOrgaFilter(Organization $organization): void
    {
        if ($this->security->isGranted('orga:see:tickets:all', $organization)) {
            $this->orgaFilters['all'][] = $organization->getId();
        } else {
            $this->orgaFilters['actor'][] = $organization->getId();
        }
    }

    /**
     * @return Ticket[]
     */
    public function getTickets(string $queryString = ''): array
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $sort = ['createdAt', 'DESC'];

        return $this->ticketRepository->findByQuery(
            $currentUser,
            $this->orgaFilters,
            Query::fromString($queryString),
            $sort,
        );
    }

    public function countTickets(string $queryString = ''): int
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        return $this->ticketRepository->countByQuery(
            $currentUser,
            $this->orgaFilters,
            Query::fromString($queryString),
        );
    }
}
