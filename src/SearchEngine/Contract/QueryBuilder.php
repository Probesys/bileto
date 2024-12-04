<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Contract;

use App\Entity;
use App\Repository;
use App\SearchEngine;
use App\Utils;
use Doctrine\ORM;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends SearchEngine\QueryBuilder<Entity\Contract>
 */
class QueryBuilder extends SearchEngine\QueryBuilder
{
    public function __construct(
        protected Repository\OrganizationRepository $organizationRepository,
        protected ORM\EntityManagerInterface $entityManager,
    ) {
    }

    protected function getFrom(): string
    {
        return Entity\Contract::class;
    }

    protected function getFromAlias(): string
    {
        return 'c';
    }

    protected function getTextField(): string
    {
        return 'name';
    }

    protected function getQualifiersMapping(): array
    {
        return [
            'status' => $this->buildStatusExpr(...),
            'org' => $this->buildOrganizationExpr(...),
            'alert' => $this->buildAlertExpr(...),
            'has:alert' => $this->buildHasAlertExpr(...),
            'no:alert' => $this->buildNoAlertExpr(...),
        ];
    }

    /**
     * @return literal-string
     */
    protected function buildStatusExpr(SearchEngine\Query\Condition $condition): string
    {
        $now = Utils\Time::now();
        $nowKey = $this->registerParameter($now);

        $exprs = [];

        $statuses = $condition->getValue();

        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        foreach ($statuses as $status) {
            if ($status === 'coming') {
                $exprs[] = "(c.startAt > :{$nowKey})";
            } elseif ($status === 'ongoing') {
                $exprs[] = $this->getOngoingDatesDql($nowKey);
            } elseif ($status === 'finished') {
                $exprs[] = "(c.endAt < :{$nowKey})";
            } else {
                // We don't fail for an invalid status, but we returns no result for this condition.
                $exprs[] = '(1 = 0)';
            }
        }

        $where = implode(' OR ', $exprs);

        if ($condition->not()) {
            return "NOT ({$where})";
        } else {
            return "({$where})";
        }
    }

    /**
     * @return literal-string
     */
    protected function buildOrganizationExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $this->processValue($condition->getValue(), function (mixed $value): array {
            $id = $this->extractId($value);

            if ($id !== null) {
                return [$id];
            }

            $organizations = $this->organizationRepository->findLike($value);

            $ids = array_map(function ($orga): int {
                return $orga->getId();
            }, $organizations);

            if ($ids) {
                return $ids;
            } else {
                return [-1];
            }
        });

        return $this->buildExpr('c.organization', $value, $condition->not());
    }

    /**
     * @return literal-string
     */
    protected function buildAlertExpr(SearchEngine\Query\Condition $condition): string
    {
        $now = Utils\Time::now();
        $nowKey = $this->registerParameter($now);

        $exprs = [];

        $alerts = $condition->getValue();

        if (!is_array($alerts)) {
            $alerts = [$alerts];
        }

        foreach ($alerts as $alert) {
            if ($alert === 'time') {
                $exprs[] = $this->getAlertTimeDql();
            } elseif ($alert === 'date') {
                $exprs[] = $this->getAlertDateDql($nowKey);
            } else {
                // We don't fail for an invalid alert, but we returns no result for this condition.
                $exprs[] = '(1 = 0)';
            }
        }

        $ongoingDql = $this->getOngoingDatesDql($nowKey);
        $alertsDql = implode(' OR ', $exprs);
        $where = "{$ongoingDql} AND ({$alertsDql})";

        if ($condition->not()) {
            return "NOT ({$where})";
        } else {
            return "({$where})";
        }
    }

    /**
     * @return literal-string
     */
    protected function buildHasAlertExpr(SearchEngine\Query\Condition $condition): string
    {
        $where = $this->getAlertAnyDql($condition);

        if ($condition->not()) {
            return "NOT ({$where})";
        } else {
            return "({$where})";
        }
    }

    /**
     * @return literal-string
     */
    protected function buildNoAlertExpr(SearchEngine\Query\Condition $condition): string
    {
        $where = $this->getAlertAnyDql($condition);

        if ($condition->not()) {
            return "({$where})";
        } else {
            return "NOT ({$where})";
        }
    }

    /**
     * @param literal-string $nowKey
     * @return literal-string
     */
    private function getOngoingDatesDql(string $nowKey): string
    {
        $startAtWhere = "c.startAt <= :{$nowKey}";
        $endAtWhere = "c.endAt >= :{$nowKey}";

        return "({$startAtWhere} AND {$endAtWhere})";
    }

    /**
     * @return literal-string
     */
    private function getAlertTimeDql(): string
    {
        list ($consumptionBuilderName, $consumptionBuilder) = $this->createSubBuilder(Entity\TimeSpent::class);
        $consumptionBuilder->select("(COALESCE(SUM({$consumptionBuilderName}.time), 0) / 60.0)");
        $consumptionBuilder->where("{$consumptionBuilderName}.contract = c");

        /** @var literal-string */
        $consumptionDql = $consumptionBuilder->getDQL();

        $alertSetWhere = "c.hoursAlert > 0";
        $alertWhere = "({$consumptionDql}) >= (c.hoursAlert * c.maxHours / 100.0)";

        return "({$alertSetWhere} AND {$alertWhere})";
    }

    /**
     * @param literal-string $nowKey
     * @return literal-string
     */
    private function getAlertDateDql(string $nowKey): string
    {
        $alertSetWhere = "c.dateAlert > 0";
        $alertWhere = ":{$nowKey} >= DATE_SUB(c.endAt, c.dateAlert, 'DAY')";

        return "({$alertSetWhere} AND {$alertWhere})";
    }

    /**
     * @return literal-string
     */
    private function getAlertAnyDql(SearchEngine\Query\Condition $condition): string
    {
        $now = Utils\Time::now();
        $nowKey = $this->registerParameter($now);

        $ongoingDql = $this->getOngoingDatesDql($nowKey);
        $alertTimeDql = $this->getAlertTimeDql();
        $alertDateDql = $this->getAlertDateDql($nowKey);

        return "{$ongoingDql} AND ({$alertTimeDql} OR {$alertDateDql})";
    }
}
