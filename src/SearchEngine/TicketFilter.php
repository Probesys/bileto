<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine;

use App\Entity\Ticket;

/**
 * @phpstan-type FilterValues array<value-of<TicketFilter::SUPPORTED_FILTERS>, FilterValue[]>
 * @phpstan-type FilterValue string|int|null
 */
class TicketFilter
{
    public const SUPPORTED_FILTERS = [
        'status', 'type',
        'assignee', 'requester', 'involves',
        'urgency', 'impact', 'priority',
    ];

    private string $text = '';

    /** @var FilterValues $filters */
    private array $filters = [];

    public static function fromQuery(Query $query): ?self
    {
        $ticketFilter = new self();

        foreach ($query->getConditions() as $condition) {
            if ($condition->isTextCondition()) {
                $ticketFilter->addTextCondition($condition);
            } elseif (
                $condition->isQualifierCondition() &&
                $condition->and() &&
                !$condition->not()
            ) {
                try {
                    $ticketFilter->addQualifierCondition($condition);
                } catch (\UnexpectedValueException $e) {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $ticketFilter;
    }

    public function toTextualQuery(): string
    {
        $textualQueryParts = [];

        if ($this->text) {
            $textualQueryParts[] = $this->text;
        }

        foreach ($this->filters as $filter => $values) {
            if ($this->isActorFilter($filter)) {
                $actorIds = [];

                foreach ($values as $id) {
                    if ($id === null) {
                        $textualQueryParts[] = "no:{$filter}";
                    } elseif ($id === '@me') {
                        $actorIds[] = '@me';
                    } else {
                        $actorIds[] = "#{$id}";
                    }
                }

                $values = $actorIds;
            }

            if ($values) {
                $values = implode(',', $values);
                $textualQueryParts[] = "{$filter}:{$values}";
            }
        }

        return implode(' ', $textualQueryParts);
    }

    public function getText(bool $escaped = false): string
    {
        if ($escaped) {
            return $this->text;
        } else {
            return stripcslashes($this->text);
        }
    }

    public function setText(string $text): void
    {
        $this->text = $this->escapeText($text);
    }

    /**
     * @return FilterValues
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return FilterValue[]
     */
    public function getFilter(string $filter): array
    {
        return $this->filters[$filter] ?? [];
    }

    /**
     * @param value-of<self::SUPPORTED_FILTERS> $filter
     * @param string[] $values
     */
    public function setFilter(string $filter, array $values): void
    {
        if ($filter === 'status') {
            $acceptedValues = Ticket::STATUSES;
            $acceptedValues[] = 'open';
            $acceptedValues[] = 'finished';

            foreach ($values as $value) {
                if (!in_array($value, $acceptedValues)) {
                    throw new \UnexpectedValueException("\"{$value}\" is not valid value for \"status\" filter");
                }
            }
        }

        if ($filter === 'type') {
            foreach ($values as $value) {
                if (!in_array($value, Ticket::TYPES)) {
                    throw new \UnexpectedValueException("\"{$value}\" is not valid value for \"type\" filter");
                }
            }
        }

        if ($filter === 'urgency' || $filter === 'impact' || $filter === 'priority') {
            foreach ($values as $value) {
                if (!in_array($value, Ticket::WEIGHTS)) {
                    throw new \UnexpectedValueException("\"{$value}\" is not valid value for \"{$filter}\" filter");
                }
            }
        }

        if ($this->isActorFilter($filter)) {
            $values = $this->processActorValues($values);
        }

        $this->filters[$filter] = $values;
    }

    private function addTextCondition(Query\Condition $condition): void
    {
        $value = $condition->getValue();

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        if ($condition->not()) {
            $value = "NOT {$value}";
        }

        if ($condition->or()) {
            $value = "OR {$value}";
        }

        if ($this->text) {
            $this->text .= ' ' . $this->escapeText($value);
        } else {
            $this->text = $this->escapeText($value);
        }
    }

    private function addQualifierCondition(Query\Condition $condition): void
    {
        $qualifier = $condition->getQualifier();
        $value = $condition->getValue();

        if ($qualifier === 'no' && $value === 'assignee') {
            $filter = 'assignee';
            $value = null;
        } else {
            $filter = $qualifier;
        }

        if (!in_array($filter, self::SUPPORTED_FILTERS)) {
            throw new \UnexpectedValueException("\"{$filter}\" filter is not supported");
        }

        /** @var value-of<self::SUPPORTED_FILTERS> */
        $filter = $filter;

        if (!empty($this->filters[$filter])) {
            throw new \UnexpectedValueException("\"{$filter}\" filter is already set");
        }

        if (is_array($value)) {
            $values = $value;
        } else {
            $values = [$value];
        }

        $this->setFilter($filter, $values);
    }

    public function isSupportedFilter(string $filter): bool
    {
        return in_array($filter, self::SUPPORTED_FILTERS);
    }

    private function isActorFilter(string $filter): bool
    {
        return (
            $filter === 'assignee' ||
            $filter === 'requester' ||
            $filter === 'involves'
        );
    }

    /**
     * @param array<string|null> $values
     *
     * @return FilterValue[]
     */
    private function processActorValues(array $values): array
    {
        return array_map(function (?string $value) {
            if ($value === null) {
                return null;
            } elseif (preg_match('/^#[\d]+$/', $value)) {
                $value = substr($value, 1);
                return intval($value);
            } elseif ($value === '@me') {
                return $value;
            } else {
                throw new \UnexpectedValueException('Unexpected actor value');
            }
        }, $values);
    }

    private function escapeText(string $text): string
    {
        return addcslashes($text, ':,()\\');
    }
}
