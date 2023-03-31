<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Utils\Queries;

class Query
{
    /** @var QueryCondition[] */
    private array $conditions = [];

    public function addCondition(QueryCondition $condition): void
    {
        $this->conditions[] = $condition;
    }

    /** @return QueryCondition[] */
    public function getConditions(): array
    {
        return $this->conditions;
    }
}
