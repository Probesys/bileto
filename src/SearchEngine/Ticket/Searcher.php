<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Ticket;

use App\Entity;
use App\SearchEngine;
use App\Security;
use App\Utils;

/**
 * @phpstan-import-type PaginationOptions from Utils\Pagination
 */
class Searcher
{
    public const QUERY_DEFAULT = 'status:open';
    public const QUERY_UNASSIGNED = 'status:open no:assignee';
    public const QUERY_OWNED = 'status:open involves:@me';

    private SearchEngine\Query $orgaQuery;

    public function __construct(
        private QueryBuilder $ticketQueryBuilder,
        private Security\Authorizer $authorizer,
    ) {
        // The default query makes sure that the SearchEngine only returns
        // tickets related to the current user.
        $this->orgaQuery = SearchEngine\Query::fromString('involves:@me');
    }

    public function setOrganization(Entity\Organization $organization): self
    {
        return $this->setOrganizations([$organization]);
    }

    /**
     * @param Entity\Organization[] $organizations
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
            $this->orgaQuery = SearchEngine\Query::fromString($queryString);
        }

        return $this;
    }

    /**
     * @param ?PaginationOptions $paginationOptions
     *
     * @return Utils\Pagination<Entity\Ticket>
     */
    public function getTickets(
        ?SearchEngine\Query $query = null,
        string $sort = '',
        ?array $paginationOptions = null
    ): Utils\Pagination {
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

        /** @var Utils\Pagination<Entity\Ticket> */
        $pagination = Utils\Pagination::paginate($queryBuilder->getQuery(), $paginationOptions);

        return $pagination;
    }

    public function countTickets(?SearchEngine\Query $query = null): int
    {
        $queries = [$this->orgaQuery];

        if ($query) {
            $queries[] = $query;
        }

        $queryBuilder = $this->ticketQueryBuilder->create($queries);
        $queryBuilder->select($queryBuilder->expr()->countDistinct('t.id'));
        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public static function queryDefault(): SearchEngine\Query
    {
        return SearchEngine\Query::fromString(self::QUERY_DEFAULT);
    }

    public static function queryUnassigned(): SearchEngine\Query
    {
        return SearchEngine\Query::fromString(self::QUERY_UNASSIGNED);
    }

    public static function queryOwned(): SearchEngine\Query
    {
        return SearchEngine\Query::fromString(self::QUERY_OWNED);
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
