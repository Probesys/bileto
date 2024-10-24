<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine;

use App\Entity;

class TicketFilter
{
    private string $text = '';
    private array $groupStatuses = [];
    private array $statuses = [];
    private array $involves = [];
    private array $assignees = [];
    private bool $unassignedOnly = false;
    private array $requesters = [];
    private array $labels = [];
    private array $priorities = [];
    private array $urgencies = [];
    private array $impacts = [];
    private string $type = '';

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getGroupStatuses(): array
    {
        return $this->groupStatuses;
    }

    public function setGroupStatuses(array $values): void
    {
        $this->groupStatuses = $values;
    }

    public function addGroupStatus(string $value): void
    {
        $this->groupStatuses[] = $value;
    }

    public function getStatuses(): array
    {
        return $this->statuses;
    }

    public function setStatuses(array $values): void
    {
        if (in_array('open', $values)) {
            $values = array_diff($values, Entity\Ticket::OPEN_STATUSES);
            $this->addGroupStatus('open');
        }

        if (in_array('finished', $values)) {
            $values = array_diff($values, Entity\Ticket::FINISHED_STATUSES);
            $this->addGroupStatus('finished');
        }

        $this->statuses = array_unique($values);
    }

    public function getInvolves(): array
    {
        return $this->involves;
    }

    public function setInvolves(array $values): void
    {
        $this->involves = $values;
    }

    public function getAssignees(): array
    {
        return $this->assignees;
    }

    public function setAssignees(array $values): void
    {
        $this->assignees = $values;
    }

    public function getUnassignedOnly(): bool
    {
        return $this->unassignedOnly;
    }

    public function setUnassignedOnly(bool $value): void
    {
        $this->unassignedOnly = $value;
    }

    public function getRequesters(): array
    {
        return $this->requesters;
    }

    public function setRequesters(array $values): void
    {
        $this->requesters = $values;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    public function addLabels(array $values): void
    {
        $labels = array_merge($this->labels, $values);
        $this->setLabels($labels);
    }

    public function setLabels(array $values): void
    {
        $this->labels = array_unique($values);
    }

    public function getPriorities(): array
    {
        return $this->priorities;
    }

    public function setPriorities(array $values): void
    {
        $this->priorities = $values;
    }

    public function getUrgencies(): array
    {
        return $this->urgencies;
    }

    public function setUrgencies(array $values): void
    {
        $this->urgencies = $values;
    }

    public function getImpacts(): array
    {
        return $this->impacts;
    }

    public function setImpacts(array $values): void
    {
        $this->impacts = $values;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType($value): void
    {
        $this->type = $value;
    }

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

        if ($this->groupStatuses || $this->statuses) {
            $values = array_merge($this->groupStatuses, $this->statuses);
            $values = implode(',', $values);
            $textualQueryParts[] = "status:{$values}";
        }

        if ($this->involves) {
            $values = $this->processActorValues($this->involves);
            $textualQueryParts[] = "involves:{$values}";
        }

        if ($this->assignees) {
            $values = $this->processActorValues($this->assignees);
            $textualQueryParts[] = "assignee:{$values}";
        }

        if ($this->unassignedOnly) {
            $textualQueryParts[] = "no:assignee";
        }

        if ($this->requesters) {
            $values = $this->processActorValues($this->requesters);
            $textualQueryParts[] = "requester:{$values}";
        }

        foreach ($this->labels as $value) {
            if (
                str_contains($value, ' ') &&
                (!str_starts_with($value, '"') || !str_ends_with($value, '"'))
            ) {
                $value = '"' . $value . '"';
            }

            $textualQueryParts[] = "label:{$value}";
        }

        if ($this->priorities) {
            $values = implode(',', $this->priorities);
            $textualQueryParts[] = "priority:{$values}";
        }

        if ($this->urgencies) {
            $values = implode(',', $this->urgencies);
            $textualQueryParts[] = "urgency:{$values}";
        }

        if ($this->impacts) {
            $values = implode(',', $this->impacts);
            $textualQueryParts[] = "impact:{$values}";
        }

        if ($this->type) {
            $textualQueryParts[] = "type:{$this->type}";
        }

        return implode(' ', $textualQueryParts);
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

        if (is_array($value)) {
            $values = $value;
        } else {
            $values = [$value];
        }

        // TODO fail if already set?
        if ($qualifier === 'status') {
            $this->setStatuses($values);
        } elseif ($qualifier === 'priority') {
            $this->setPriorities($values);
        } elseif ($qualifier === 'urgency') {
            $this->setUrgencies($values);
        } elseif ($qualifier === 'impact') {
            $this->setImpacts($values);
        } elseif ($qualifier === 'involves') {
            $this->setInvolves($values);
        } elseif ($qualifier === 'assignee') {
            $this->setAssignees($values);
        } elseif ($qualifier === 'requester') {
            $this->setRequesters($values);
        } elseif ($qualifier === 'label') {
            $this->addLabels($values);
        } elseif ($qualifier === 'type') {
            $this->setType($values[0]);
        } elseif ($qualifier === 'no' && $values[0] === 'assignee') {
            $this->setUnassignedOnly(true);
        } else {
            throw new \UnexpectedValueException("\"{$qualifier}\" qualifier is not supported");
        }
    }

    private function processActorValues(array $values): string
    {
        $actorIds = [];

        foreach ($values as $id) {
            if ($id === '@me') {
                $actorIds[] = '@me';
            } else {
                $actorIds[] = "#{$id}";
            }
        }

        return implode(',', $actorIds);
    }

    private function escapeText(string $text): string
    {
        return addcslashes($text, ':,()\\');
    }
}
