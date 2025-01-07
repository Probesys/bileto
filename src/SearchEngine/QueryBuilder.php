<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine;

use App\Entity;
use Doctrine\ORM;

/**
 * A QueryBuilder transforms a list of `Query` into a Doctrine `QueryBuilder`.
 *
 * @template T of Entity\EntityInterface
 */
abstract class QueryBuilder
{
    protected int $subBuilderSequence = 0;

    /** @var array<literal-string, mixed> */
    protected array $parameters;

    protected int $querySequence;

    public function __construct(
        protected ORM\EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Transform a list of queries into a Doctrine query builder.
     *
     * This is the entrypoint of this class.
     *
     * @param Query[] $queries
     */
    public function create(array $queries): ORM\QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select(static::getFromAlias());
        $queryBuilder->from(static::getFrom(), static::getFromAlias());

        $this->subBuilderSequence = 0;

        foreach ($queries as $sequence => $query) {
            if ($query->getString() === '') {
                continue;
            }

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
     * @return array{literal-string, array<literal-string, mixed>}
     */
    public function buildQuery(Query $query, int $querySequence = 0): array
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

    /**
     * Return the class name of the entity to search.
     *
     * @return class-string<T>
     */
    abstract protected function getFrom(): string;

    /**
     * Return the alias of the class name that will be used in the query builder.
     *
     * @return literal-string
     */
    abstract protected function getFromAlias(): string;

    /**
     * Return the name of the entity's text field.
     *
     * This is the field that will be searched when searching for full text.
     *
     * @return literal-string
     */
    abstract protected function getTextField(): string;

    /**
     * Return the name of the entity's id field (usually "id").
     *
     * This is the field that will be searched when using the #id syntax.
     *
     * @return literal-string
     */
    protected function getIdField(): string
    {
        return 'id';
    }

    /**
     * Return a list of qualifiers and their corresponding expression builder method.
     *
     * Each key can be either a qualifier (e.g. `status`) or a qualifier and a
     * value (e.g. `status:open`).
     *
     * The values must be a reference to a method which takes a Query\Condition
     * as a single argument, and returns a literal string corresponding to a
     * DQL "where" expression (e.g. `t.status = :key`).
     *
     * @return array<string, callable(Query\Condition): literal-string>
     */
    abstract protected function getQualifiersMapping(): array;

    /**
     * Return an expression that can be used in a DQL where condition.
     *
     * The value can be a standard value (the expression operator will be `=`
     * or `!=`), an array (`IN` / `NOT IN`) or null (`IS NULL` / `IS NOT
     * NULL`).
     *
     * @param literal-string $field
     * @return literal-string
     */
    protected function buildExpr(string $field, mixed $value, bool $not): string
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
     * Return a EMPTY / NOT EMPTY expression for the given field.
     *
     * @param literal-string $field
     * @return literal-string
     */
    protected function buildEmptyExpr(string $field, bool $not): string
    {
        if ($not) {
            return "{$field} IS NOT EMPTY";
        } else {
            return "{$field} IS EMPTY";
        }
    }

    /**
     * Return a LIKE / NOT LIKE expression. The comparison is case insensitive.
     *
     * @param literal-string $field
     * @return literal-string
     */
    protected function buildExprLike(string $field, string $value, bool $not): string
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
     * Return a DQL sub-query that returns a list of entity ids for which the
     * ids of the many-to-many associations match the given value.
     *
     * It does something similar to:
     *
     *     SELECT entity.id
     *     JOIN entity.field
     *     WHERE entity.field.id = value
     *
     * It uses the buildExpr() method to match the value.
     *
     * @param class-string<Entity\EntityInterface> $entity
     * @param literal-string $field
     * @return literal-string
     */
    protected function getManyToManyDql(string $entity, string $field, mixed $value): string
    {
        list ($subBuilderName, $subBuilder) = $this->createSubBuilder($entity);
        $subBuilder->select("{$subBuilderName}.id");
        $subBuilder->innerJoin("{$subBuilderName}.{$field}", "{$subBuilderName}_{$field}");

        $expr = $this->buildExpr("{$subBuilderName}_{$field}.id", $value, false);
        $subBuilder->where($expr);

        /** @var literal-string */
        $dql = $subBuilder->getDQL();

        return $dql;
    }

    /**
     * Process a single value or a list of values with the given callback.
     *
     * A callback takes a single value as argument and can return a list of
     * values. The processed values are merged into a single array.
     *
     * If the final list of processed values contain only one element, the
     * element is returned directy. Otherwise, the list is returned.
     *
     * @param mixed|mixed[] $values
     * @param callable(mixed): mixed[] $callback
     *
     * @return mixed|mixed[]
     */
    protected function processValue(mixed $values, callable $callback): mixed
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        $valuesToReturn = [];

        foreach ($values as $value) {
            $processedValues = $callback($value);
            $valuesToReturn = array_merge($valuesToReturn, $processedValues);
        }

        if (count($valuesToReturn) === 1) {
            return $valuesToReturn[0];
        } else {
            return $valuesToReturn;
        }
    }

    /**
     * Extract an integer id from a value matching the format `#<int>`.
     *
     * Null is returned if the value doesn't match the format.
     */
    protected function extractId(string $value): ?int
    {
        if (preg_match('/^#[\d]+$/', $value)) {
            $value = substr($value, 1);
            return intval($value);
        } else {
            return null;
        }
    }

    /**
     * Register the value in the list of the query builder parameters.
     *
     * It returns the parameter name. This parameter can safely be used in DQL
     * expressions.
     *
     * @return literal-string
     */
    protected function registerParameter(mixed $value): string
    {
        $paramNumber = count($this->parameters);
        /** @var literal-string */
        $key = "q{$this->querySequence}p{$paramNumber}";
        $this->parameters[$key] = $value;
        return $key;
    }

    /**
     * Create a Doctrine QueryBuilder for the given entity.
     *
     * This can be useful to create sub-requests.
     *
     * The method returns an array containing two elements: the builder name
     * and the query builder object. The name can safely be used in DQL
     * expression.
     *
     * @param class-string $entity<Entity\EntityInterface>
     * @return array{literal-string, ORM\QueryBuilder}
     */
    protected function createSubBuilder(string $entity): array
    {
        /** @var literal-string */
        $subBuilderName = "sub_table_{$this->subBuilderSequence}";
        $this->subBuilderSequence += 1;

        $subBuilder = $this->entityManager->createQueryBuilder();
        $subBuilder->from($entity, $subBuilderName);

        return [$subBuilderName, $subBuilder];
    }

    /**
     * Return a "where" expression corresponding to the given query.
     *
     * @return literal-string
     */
    private function buildWhere(Query $query): string
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

    /**
     * Return a DQL expression that corresponds to the given text condition.
     *
     * @return literal-string
     */
    private function buildTextExpr(Query\Condition $condition): string
    {
        if (!$condition->isTextCondition()) {
            throw new \LogicException('A "text" condition must be passed.');
        }

        $value = $condition->getValue();

        $idField = static::getFromAlias() . '.' . static::getIdField();
        $textField = static::getFromAlias() . '.' . static::getTextField();

        if (is_array($value)) {
            $exprs = [];

            foreach ($value as $v) {
                $id = $this->extractId($v);
                if ($id !== null) {
                    $exprs[] = $this->buildExpr($idField, $id, false);
                } else {
                    $exprs[] = $this->buildExprLike($textField, $v, false);
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
                return $this->buildExpr($idField, $id, $condition->not());
            } else {
                return $this->buildExprLike($textField, $value, $condition->not());
            }
        }
    }

    /**
     * Return a DQL expression that corresponds to the given qualifier condition.
     *
     * @return literal-string
     */
    private function buildQualifierExpr(Query\Condition $condition): string
    {
        if (!$condition->isQualifierCondition()) {
            throw new \LogicException('A "qualifier" condition must be passed.');
        }

        $qualifier = $condition->getQualifier();
        $value = $condition->getValue();

        $qualifierMapping = static::getQualifiersMapping();

        $qualifierKey = $qualifier;
        $qualifierValueKey = '';
        if (is_string($value)) {
            $qualifierValueKey = "{$qualifierKey}:{$value}";
        }

        $qualifierExprBuilder = $qualifierMapping[$qualifierValueKey] ?? null;

        if ($qualifierExprBuilder === null) {
            $qualifierExprBuilder = $qualifierMapping[$qualifierKey] ?? null;
        }

        if ($qualifierExprBuilder === null) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            throw new \UnexpectedValueException("Unexpected \"{$qualifier}\" qualifier with value \"{$value}\"");
        }

        return $qualifierExprBuilder($condition);
    }

    /**
     * Return a DQL expression that corresponds to the given query condition.
     *
     * @return literal-string
     */
    private function buildQueryExpr(Query\Condition $condition): string
    {
        if (!$condition->isQueryCondition()) {
            throw new \LogicException('A "query" condition must be passed.');
        }

        $subQuery = $condition->getQuery();

        if ($condition->not()) {
            return "NOT ({$this->buildWhere($subQuery)})";
        } else {
            return "({$this->buildWhere($subQuery)})";
        }
    }
}
