<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine;

use App\Entity\Organization;
use App\Entity\Ticket;
use App\SearchEngine\QueryBuilder\TicketQueryBuilder;
use App\Security\Authorizer;
use App\Utils\Pagination;

/**
 * @phpstan-import-type PaginationOptions from Pagination
 */
class TicketSearcher
{
    public const QUERY_DEFAULT = 'status:open';
    public const QUERY_UNASSIGNED = 'status:open no:assignee';
    public const QUERY_OWNED = 'status:open involves:@me';

    private TicketQueryBuilder $ticketQueryBuilder;

    private Query $orgaQuery;

    private Authorizer $authorizer;

    public function __construct(TicketQueryBuilder $ticketQueryBuilder, Authorizer $authorizer)
    {
        $this->ticketQueryBuilder = $ticketQueryBuilder;
        $this->authorizer = $authorizer;

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
            if ($this->authorizer->isGranted('orga:see:tickets:all', $organization)) {
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
     * @param ?PaginationOptions $paginationOptions
     *
     * @return Pagination<Ticket>
     */
    public function getTickets(?Query $query = null, string $sort = '', ?array $paginationOptions = null): Pagination
    {
        if ($paginationOptions === null) {
            $paginationOptions = [
                'page' => 1,
                'maxResults' => 25,
            ];
        }

        $sort = $this->processSort($sort);

        $queries = [$this->orgaQuery];

        if ($query) {
            $queries[] = $query;
        }

        $queryBuilder = $this->ticketQueryBuilder->create($queries);
        $queryBuilder->orderBy("t.{$sort[0]}", $sort[1]);

        /** @var Pagination<Ticket> */
        $pagination = Pagination::paginate($queryBuilder->getQuery(), $paginationOptions);

        return $pagination;
    }

    public function countTickets(?Query $query = null): int
    {
        $queries = [$this->orgaQuery];

        if ($query) {
            $queries[] = $query;
        }

        $queryBuilder = $this->ticketQueryBuilder->create($queries);
        $queryBuilder->select($queryBuilder->expr()->count('t.id'));
        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public static function queryUnassigned(): Query
    {
        return Query::fromString(self::QUERY_UNASSIGNED);
    }

    public static function queryOwned(): Query
    {
        return Query::fromString(self::QUERY_OWNED);
    }

    /**
     * @return array{string, 'ASC'|'DESC'}
     */
    private function processSort(string $sort): array
    {
        if ($sort === 'title-asc') {
            return ['title', 'ASC'];
        } elseif ($sort === 'title-desc') {
            return ['title', 'DESC'];
        } elseif ($sort === 'created-asc') {
            return ['createdAt', 'ASC'];
        } elseif ($sort === 'created-desc') {
            return ['createdAt', 'DESC'];
        } elseif ($sort === 'updated-asc') {
            return ['updatedAt', 'ASC'];
        } else {
            return ['updatedAt', 'DESC'];
        }
    }
}
