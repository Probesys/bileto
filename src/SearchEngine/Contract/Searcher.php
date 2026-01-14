<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Contract;

use App\Entity;
use App\Repository;
use App\SearchEngine;
use App\Security as AppSecurity;
use App\Utils;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @phpstan-import-type PaginationOptions from Utils\Pagination
 */
class Searcher
{
    public const QUERY_DEFAULT = '-status:finished';

    private SearchEngine\Query $orgaQuery;

    public function __construct(
        private QueryBuilder $contractQueryBuilder,
        private Repository\OrganizationRepository $organizationRepository,
        private AppSecurity\Authorizer $authorizer,
        private Security $security,
    ) {
        /** @var Entity\User */
        $user = $this->security->getUser();
        $organizations = $this->organizationRepository->findAuthorizedOrganizations($user);

        $this->setOrganizations($organizations);
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
        $organizations = array_filter($organizations, function (Entity\Organization $organization): bool {
            return $this->authorizer->isGranted('orga:see:contracts', $organization);
        });

        $organizationIds = array_map(function (Entity\Organization $organization): string {
            return "#{$organization->getId()}";
        }, $organizations);

        if ($organizationIds) {
            $organizationIds = implode(',', $organizationIds);
            $queryString = "(org:{$organizationIds})";
            $this->orgaQuery = SearchEngine\Query::fromString($queryString);
        } else {
            $this->orgaQuery = SearchEngine\Query::fromString('org:#-1');
        }

        return $this;
    }

    /**
     * @param ?PaginationOptions $paginationOptions
     *
     * @return Utils\Pagination<Entity\Contract>
     */
    public function getContracts(
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

        $queries = [$this->orgaQuery];

        if ($query) {
            $queries[] = $query;
        }

        $queryBuilder = $this->contractQueryBuilder->create($queries);

        $sorts = $this->processSort($sort);
        foreach ($sorts as $sort) {
            $queryBuilder->addOrderBy("c.{$sort[0]}", $sort[1]);
        }

        /** @var Utils\Pagination<Entity\Contract> */
        $pagination = Utils\Pagination::paginate($queryBuilder->getQuery(), $paginationOptions);

        return $pagination;
    }

    public function countContracts(?SearchEngine\Query $query = null): int
    {
        $queries = [$this->orgaQuery];

        if ($query) {
            $queries[] = $query;
        }

        $queryBuilder = $this->contractQueryBuilder->create($queries);
        $queryBuilder->select($queryBuilder->expr()->countDistinct('c.id'));
        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public static function queryDefault(): SearchEngine\Query
    {
        return SearchEngine\Query::fromString(self::QUERY_DEFAULT);
    }

    /**
     * @return array<array{string, 'ASC'|'DESC'}>
     */
    private function processSort(string $sort): array
    {
        if ($sort === 'name-asc') {
            return [
                ['name', 'ASC'],
            ];
        } elseif ($sort === 'name-desc') {
            return [
                ['name', 'DESC'],
            ];
        } elseif ($sort === 'end-asc') {
            return [
                ['endAt', 'ASC'],
                ['name', 'ASC'],
            ];
        } else {
            return [
                ['endAt', 'DESC'],
                ['name', 'ASC'],
            ];
        }
    }
}
