<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Query;

use App\SearchEngine\Query;

class Condition
{
    public const TYPES = ['text', 'id', 'qualifier', 'query'];
    public const OPERATORS = ['and', 'or'];

    /** @var value-of<self::TYPES> */
    private string $type;

    /** @var value-of<self::OPERATORS> */
    private string $operator;

    /** @var non-empty-string|non-empty-string[]|null */
    private mixed $value;

    private ?string $qualifier;

    private ?Query $query;

    private bool $not;

    /**
     * @param value-of<self::TYPES> $type
     * @param value-of<self::OPERATORS> $operator
     * @param non-empty-string|non-empty-string[]|null $value
     */
    private function __construct(
        string $type,
        string $operator,
        mixed $value,
        ?string $qualifier,
        ?Query $query,
        bool $not,
    ) {
        $this->type = $type;
        $this->operator = $operator;
        $this->not = $not;
        $this->value = $value;
        $this->qualifier = $qualifier;
        $this->query = $query;
    }

    /**
     * @param value-of<self::OPERATORS> $operator
     * @param non-empty-string|non-empty-string[] $value
     */
    public static function textCondition(string $operator, mixed $value, bool $not): self
    {
        return new self('text', $operator, $value, null, null, $not);
    }

    /**
     * @param value-of<self::OPERATORS> $operator
     * @param non-empty-string $value
     */
    public static function idCondition(string $operator, string $value, bool $not): self
    {
        if (mb_strlen($value) < 2 || !str_starts_with($value, '#')) {
            throw new \LogicException('Id conditions must contain a value starting by a #');
        }

        /** @var non-empty-string $value */
        $value = substr($value, 1);
        return new self('id', $operator, $value, null, null, $not);
    }

    /**
     * @param value-of<self::OPERATORS> $operator
     * @param non-empty-string|non-empty-string[] $value
     */
    public static function qualifierCondition(string $operator, string $qualifier, mixed $value, bool $not): self
    {
        return new self('qualifier', $operator, $value, $qualifier, null, $not);
    }

    /**
     * @param value-of<self::OPERATORS> $operator
     */
    public static function queryCondition(string $operator, Query $query, bool $not): self
    {
        return new self('query', $operator, null, null, $query, $not);
    }

    public function isTextCondition(): bool
    {
        return $this->type === 'text';
    }

    public function isQualifierCondition(): bool
    {
        return $this->type === 'qualifier';
    }

    public function isIdCondition(): bool
    {
        return $this->type === 'id';
    }

    public function isQueryCondition(): bool
    {
        return $this->type === 'query';
    }

    public function and(): bool
    {
        return $this->operator === 'and';
    }

    public function or(): bool
    {
        return $this->operator === 'or';
    }

    /**
     * @return non-empty-string|non-empty-string[]
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getQualifier(): string
    {
        return $this->qualifier;
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function not(): bool
    {
        return $this->not;
    }
}
