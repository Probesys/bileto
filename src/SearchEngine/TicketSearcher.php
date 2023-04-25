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

    private Query $orgaQuery;

    private Security $security;

    public function __construct(TicketRepository $ticketRepository, Security $security)
    {
        $this->ticketRepository = $ticketRepository;
        $this->security = $security;

        // The default query makes sure that the SearchEngine only returns
        // tickets related to the current user.
        $this->orgaQuery = Query::fromString('involves:@me');
    }

    public function setOrganization(Organization $organization): self
    {
        return $this->setOrganizations([$organization]);
    }

    /**
     * @param Organization[] $organizations
     */
    public function setOrganizations(array $organizations): self
    {
        $queries = [];

        $permissions = [
            'all' => [],
            'involves' => [],
        ];

        foreach ($organizations as $organization) {
            if ($this->security->isGranted('orga:see:tickets:all', $organization)) {
                $permissions['all'][] = "#{$organization->getId()}";
            } else {
                // Note that we don't check for the orga:see permission here.
                // We consider that a user should be able to access the tickets
                // in which he's involved whether he has access to the
                // organization or not.
                $permissions['involves'][] = "#{$organization->getId()}";
            }
        }

        $listOrgaIds = implode(',', $permissions['all']);
        if ($listOrgaIds) {
            $queries[] = "(org:{$listOrgaIds})";
        }

        $listOrgaIds = implode(',', $permissions['involves']);
        if ($listOrgaIds) {
            $queries[] = "(org:{$listOrgaIds} AND involves:@me)";
        }

        if (!empty($queries)) {
            $queryString = implode(' OR ', $queries);
            $this->orgaQuery = Query::fromString($queryString);
        }

        return $this;
    }

    /**
     * @return Ticket[]
     */
    public function getTickets(string $queryString = ''): array
    {
        $sort = ['createdAt', 'DESC'];

        $queries = [$this->orgaQuery];
        $query = Query::fromString($queryString);
        if ($query) {
            $queries[] = $query;
        }

        return $this->ticketRepository->findByQueries($queries, $sort);
    }

    public function countTickets(string $queryString = ''): int
    {
        $queries = [$this->orgaQuery];
        $query = Query::fromString($queryString);
        if ($query) {
            $queries[] = $query;
        }

        return $this->ticketRepository->countByQueries($queries);
    }
}
