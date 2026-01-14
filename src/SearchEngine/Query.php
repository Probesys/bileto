<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine;

class Query
{
    private string $string = '';

    /** @var Query\Condition[] */
    private array $conditions = [];

    public function addCondition(Query\Condition $condition): void
    {
        $this->conditions[] = $condition;
    }

    /** @return Query\Condition[] */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public static function fromString(string $queryString): Query
    {
        if (!$queryString) {
            return new self();
        }

        $tokenizer = new Query\Tokenizer();
        $parser = new Query\Parser();
        $tokens = $tokenizer->tokenize($queryString);
        $query = $parser->parse($tokens);
        $query->string = $queryString;
        return $query;
    }

    public function getString(): string
    {
        return $this->string;
    }
}
