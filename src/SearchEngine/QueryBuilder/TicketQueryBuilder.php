<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\QueryBuilder;

use App\Entity;
use App\Repository;
use App\SearchEngine;
use App\Utils;
use Doctrine\ORM;
use Symfony\Bundle\SecurityBundle\Security;

class TicketQueryBuilder
{
    private int $subBuilderSequence = 0;

    /** @var array<string, mixed> */
    private array $parameters;

    private int $querySequence;

    public function __construct(
        private Repository\LabelRepository $labelRepository,
        private Repository\UserRepository $userRepository,
        private Repository\OrganizationRepository $organizationRepository,
        private Security $security,
        private ORM\EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param SearchEngine\Query[] $queries
     */
    public function create(array $queries): ORM\QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('t');
        $queryBuilder->from('App\Entity\Ticket', 't');

        $this->subBuilderSequence = 0;

        foreach ($queries as $sequence => $query) {
            list($whereQuery, $parameters) = $this->buildQuery($query, $sequence);

            $queryBuilder->andWhere($whereQuery);

            foreach ($parameters as $key => $value) {
                $queryBuilder->setParameter($key, $value);
            }
        }

        $this->subBuilderSequence = 0;

        return $queryBuilder;
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    public function buildQuery(SearchEngine\Query $query, int $querySequence = 0): array
    {
        $this->parameters = [];
        $this->querySequence = $querySequence;

        // Build the Doctrine Query and retrieve parameters.
        $where = $this->buildWhere($query);
        $parameters = $this->parameters;

        // Reset the attributes for the next query and to free memory.
        $this->parameters = [];
        $this->querySequence = 0;

        return [$where, $parameters];
    }

    private function buildWhere(SearchEngine\Query $query): string
    {
        $where = '';

        foreach ($query->getConditions() as $condition) {
            $expr = '';

            if ($condition->isTextCondition()) {
                $expr = $this->buildTextExpr($condition);
            } elseif ($condition->isQualifierCondition()) {
                $expr = $this->buildQualifierExpr($condition);
            } elseif ($condition->isQueryCondition()) {
                $expr = $this->buildQueryExpr($condition);
            }

            if (!$expr) {
                throw new \LogicException('A condition is defective as it generates an empty expression');
            }

            if ($where === '') {
                $where = $expr;
            } elseif ($condition->and()) {
                $where .= " AND {$expr}";
            } else {
                $where .= " OR {$expr}";
            }
        }

        return $where;
    }

    private function buildTextExpr(SearchEngine\Query\Condition $condition): string
    {
        $value = $condition->getValue();

        if (is_array($value)) {
            $exprs = [];

            foreach ($value as $v) {
                $id = $this->extractId($v);
                if ($id !== null) {
                    $exprs[] = $this->buildExpr('t.id', $id, false);
                } else {
                    $exprs[] = $this->buildExprLike('t.title', $v, false);
                }
            }

            $where = implode(' OR ', $exprs);

            if ($condition->not()) {
                return "NOT ({$where})";
            } else {
                return "({$where})";
            }
        } else {
            $id = $this->extractId($value);

            if ($id !== null) {
                return $this->buildExpr('t.id', $id, $condition->not());
            } else {
                return $this->buildExprLike('t.title', $value, $condition->not());
            }
        }
    }

    private function buildQualifierExpr(SearchEngine\Query\Condition $condition): string
    {
        $qualifier = $condition->getQualifier();
        $value = $condition->getValue();

        if ($qualifier === 'status') {
            $value = $this->processStatusQualifier($value);
            return $this->buildExpr('t.status', $value, $condition->not());
        } elseif ($qualifier === 'assignee' || $qualifier === 'requester') {
            $value = $this->processActorQualifier($value);
            $field = "COALESCE(IDENTITY(t.{$qualifier}), 0)";
            return $this->buildExpr($field, $value, $condition->not());
        } elseif ($qualifier === 'observer') {
            $value = $this->processActorQualifier($value);

            $subBuilder = $this->buildManyToManyQueryBuilder('App\Entity\Ticket', 'observers', $value);

            if ($condition->not()) {
                return "t.id NOT IN ({$subBuilder->getDQL()})";
            } else {
                return "t.id IN ({$subBuilder->getDQL()})";
            }
        } elseif ($qualifier === 'involves') {
            $value = $this->processActorQualifier($value);

            $assigneeWhere = $this->buildExpr('COALESCE(IDENTITY(t.assignee), 0)', $value, false);
            $requesterWhere = $this->buildExpr('COALESCE(IDENTITY(t.requester), 0)', $value, false);

            $subTeamBuilder = $this->buildManyToManyQueryBuilder('App\Entity\Team', 'agents', $value);
            $teamWhere = "COALESCE(IDENTITY(t.team), 0) IN ({$subTeamBuilder->getDQL()})";

            $subObserversBuilder = $this->buildManyToManyQueryBuilder('App\Entity\Ticket', 'observers', $value);
            $observersWhere = "t.id IN ({$subObserversBuilder->getDQL()})";

            $where = "{$assigneeWhere} OR {$requesterWhere} OR {$teamWhere} OR {$observersWhere}";

            if ($condition->not()) {
                return "NOT ({$where})";
            } else {
                return "({$where})";
            }
        } elseif ($qualifier === 'org') {
            $value = $this->processOrganizationQualifier($value);
            return $this->buildExpr('t.organization', $value, $condition->not());
        } elseif (
            $qualifier === 'uid' ||
            $qualifier === 'type' ||
            $qualifier === 'urgency' ||
            $qualifier === 'impact' ||
            $qualifier === 'priority'
        ) {
            return $this->buildExpr('t.' . $qualifier, $value, $condition->not());
        } elseif ($qualifier === 'contract') {
            $value = $this->processContractQualifier($value);

            $subBuilder = $this->buildManyToManyQueryBuilder('App\Entity\Ticket', 'contracts', $value);

            if ($condition->not()) {
                return "t.id NOT IN ({$subBuilder->getDQL()})";
            } else {
                return "t.id IN ({$subBuilder->getDQL()})";
            }
        } elseif ($qualifier === 'label') {
            $value = $this->processLabelQualifier($value);

            $subBuilder = $this->buildManyToManyQueryBuilder('App\Entity\Ticket', 'labels', $value);

            if ($condition->not()) {
                return "t.id NOT IN ({$subBuilder->getDQL()})";
            } else {
                return "t.id IN ({$subBuilder->getDQL()})";
            }
        } elseif ($qualifier === 'no' && ($value === 'assignee' || $value === 'solution')) {
            return $this->buildExpr('t.' . $value, null, $condition->not());
        } elseif ($qualifier === 'no' && $value === 'contract') {
            return $this->buildEmptyExpr('t.contracts', $condition->not());
        } elseif ($qualifier === 'no' && $value === 'label') {
            return $this->buildEmptyExpr('t.labels', $condition->not());
        } elseif ($qualifier === 'has' && ($value === 'assignee' || $value === 'solution')) {
            return $this->buildExpr('t.' . $value, null, !$condition->not());
        } elseif ($qualifier === 'has' && $value === 'contract') {
            return $this->buildEmptyExpr('t.contracts', !$condition->not());
        } elseif ($qualifier === 'has' && $value === 'label') {
            return $this->buildEmptyExpr('t.labels', !$condition->not());
        } else {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            throw new \UnexpectedValueException("Unexpected \"{$qualifier}\" qualifier with value \"{$value}\"");
        }
    }

    private function buildQueryExpr(SearchEngine\Query\Condition $condition): string
    {
        $subQuery = $condition->getQuery();

        if ($condition->not()) {
            return "NOT ({$this->buildWhere($subQuery)})";
        } else {
            return "({$this->buildWhere($subQuery)})";
        }
    }

    /**
     * @param literal-string $field
     */
    private function buildExpr(string $field, mixed $value, bool $not): string
    {
        if ($value === null && $not) {
            return "{$field} IS NOT NULL";
        } elseif ($value === null) {
            return "{$field} IS NULL";
        } elseif (is_array($value) && $not) {
            $key = $this->registerParameter($value);
            return "{$field} NOT IN (:{$key})";
        } elseif (is_array($value)) {
            $key = $this->registerParameter($value);
            return "{$field} IN (:{$key})";
        } elseif ($not) {
            $key = $this->registerParameter($value);
            return "{$field} != :{$key}";
        } else {
            $key = $this->registerParameter($value);
            return "{$field} = :{$key}";
        }
    }

    /**
     * @param literal-string $field
     */
    private function buildEmptyExpr(string $field, bool $not): string
    {
        if ($not) {
            return "{$field} IS NOT EMPTY";
        } else {
            return "{$field} IS EMPTY";
        }
    }

    /**
     * @param literal-string $field
     */
    private function buildExprLike(string $field, string $value, bool $not): string
    {
        $value = mb_strtolower($value);
        $key = $this->registerParameter("%{$value}%");
        if ($not) {
            return "LOWER({$field}) NOT LIKE :{$key}";
        } else {
            return "LOWER({$field}) LIKE :{$key}";
        }
    }

    /**
     * @param class-string $entity
     * @param literal-string $field
     */
    private function buildManyToManyQueryBuilder(string $entity, string $field, mixed $value): ORM\QueryBuilder
    {
        list ($subBuilderName, $subBuilder) = $this->createSubBuilder($entity);
        $subBuilder->innerJoin("{$subBuilderName}.{$field}", "{$subBuilderName}_{$field}");

        $expr = $this->buildExpr("{$subBuilderName}_{$field}.id", $value, false);
        $subBuilder->where($expr);

        return $subBuilder;
    }

    /**
     * @param string|string[] $value
     *
     * @return string|string[]
     */
    private function processStatusQualifier(mixed $value): mixed
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $valuesToReturn = [];

        foreach ($value as $v) {
            if ($v === 'open') {
                $valuesToReturn = array_merge($valuesToReturn, Entity\Ticket::OPEN_STATUSES);
            } elseif ($v === 'finished') {
                $valuesToReturn = array_merge($valuesToReturn, Entity\Ticket::FINISHED_STATUSES);
            } else {
                $valuesToReturn[] = $v;
            }
        }

        if (count($valuesToReturn) === 1) {
            return $valuesToReturn[0];
        } else {
            return $valuesToReturn;
        }
    }

    /**
     * @param string|string[] $value
     *
     * @return int|int[]
     */
    private function processActorQualifier(mixed $value): mixed
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $valuesToReturn = [];

        foreach ($value as $v) {
            $id = $this->extractId($v);
            if ($id !== null) {
                $ids = [$id];
            } elseif ($v === '@me') {
                /** @var Entity\User $currentUser */
                $currentUser = $this->security->getUser();
                $ids = [$currentUser->getId()];
            } else {
                $users = $this->userRepository->findLike($v);

                $ids = array_map(function ($user): int {
                    return $user->getId();
                }, $users);
            }

            if ($ids) {
                $valuesToReturn = array_merge($valuesToReturn, $ids);
            } else {
                $valuesToReturn[] = -1;
            }
        }

        if (count($valuesToReturn) === 1) {
            return $valuesToReturn[0];
        } else {
            return $valuesToReturn;
        }
    }

    /**
     * @param string|string[] $value
     *
     * @return int|int[]
     */
    private function processOrganizationQualifier(mixed $value): mixed
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $valuesToReturn = [];

        foreach ($value as $v) {
            $id = $this->extractId($v);
            if ($id !== null) {
                $ids = [$id];
            } else {
                $organizations = $this->organizationRepository->findLike($v);

                $ids = array_map(function ($orga): int {
                    return $orga->getId();
                }, $organizations);
            }

            if ($ids) {
                $valuesToReturn = array_merge($valuesToReturn, $ids);
            } else {
                $valuesToReturn[] = -1;
            }
        }

        if (count($valuesToReturn) === 1) {
            return $valuesToReturn[0];
        } else {
            return $valuesToReturn;
        }
    }

    /**
     * @param string|string[] $value
     *
     * @return int|int[]
     */
    private function processContractQualifier(mixed $value): mixed
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $valuesToReturn = [];

        foreach ($value as $v) {
            $id = $this->extractId($v);
            if ($id) {
                $valuesToReturn[] = $id;
            } else {
                $valuesToReturn[] = -1;
            }
        }

        if (count($valuesToReturn) === 1) {
            return $valuesToReturn[0];
        } else {
            return $valuesToReturn;
        }
    }

    /**
     * @param string|string[] $values
     *
     * @return int|int[]
     */
    private function processLabelQualifier(mixed $values): mixed
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $valuesToReturn = [];

        foreach ($values as $value) {
            $labels = $this->labelRepository->findByName($value);

            $ids = array_map(function ($label): int {
                return $label->getId();
            }, $labels);

            if ($ids) {
                $valuesToReturn = array_merge($valuesToReturn, $ids);
            } else {
                $valuesToReturn[] = -1;
            }
        }

        if (count($valuesToReturn) === 1) {
            return $valuesToReturn[0];
        } else {
            return $valuesToReturn;
        }
    }

    private function extractId(string $value): ?int
    {
        if (preg_match('/^#[\d]+$/', $value)) {
            $value = substr($value, 1);
            return intval($value);
        } else {
            return null;
        }
    }

    private function registerParameter(mixed $value): string
    {
        $paramNumber = count($this->parameters);
        $key = "q{$this->querySequence}p{$paramNumber}";
        $this->parameters[$key] = $value;
        return $key;
    }

    /**
     * @param class-string $entity
     *
     * @return array{literal-string, ORM\QueryBuilder}
     */
    private function createSubBuilder(string $entity): array
    {
        /** @var literal-string */
        $subBuilderName = "sub_table_{$this->subBuilderSequence}";
        $this->subBuilderSequence += 1;

        $subBuilder = $this->entityManager->createQueryBuilder();
        $subBuilder->select("{$subBuilderName}.id");
        $subBuilder->from($entity, $subBuilderName);

        return [$subBuilderName, $subBuilder];
    }
}
