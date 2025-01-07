<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\SearchEngine\Ticket;

use App\Entity;
use Doctrine\Common\Collections;

class QuickSearchFilter
{
    private string $text = '';

    /** @var string[] */
    private array $groupStatuses = [];

    /** @var string[] */
    private array $statuses = [];

    /** @var Collections\Collection<int, Entity\User> */
    private Collections\Collection $involves;

    /** @var Collections\Collection<int, Entity\User> */
    private Collections\Collection $assignees;

    private bool $unassignedOnly = false;

    /** @var Collections\Collection<int, Entity\User> */
    private Collections\Collection $requesters;

    /** @var Collections\Collection<int, Entity\Label> */
    private Collections\Collection $labels;

    /** @var string[] */
    private array $priorities = [];

    /** @var string[] */
    private array $urgencies = [];

    /** @var string[] */
    private array $impacts = [];

    private string $type = '';

    public function __construct()
    {
        $this->involves = new Collections\ArrayCollection();
        $this->assignees = new Collections\ArrayCollection();
        $this->requesters = new Collections\ArrayCollection();
        $this->labels = new Collections\ArrayCollection();
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
        $this->text = addcslashes($text, ':,()\\');
    }

    public function addText(string $text): void
    {
        $escapedText = addcslashes($text, ':,()\\');

        if ($this->text) {
            $this->text .= ' ' . $escapedText;
        } else {
            $this->text = $escapedText;
        }
    }

    /**
     * @return string[]
     */
    public function getGroupStatuses(): array
    {
        return $this->groupStatuses;
    }

    /**
     * @param string[] $values
     */
    public function setGroupStatuses(array $values): void
    {
        $this->groupStatuses = $values;
        $this->normalizeStatuses();
    }

    /**
     * @return string[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @param string[] $values
     */
    public function setStatuses(array $values): void
    {
        $this->statuses = $values;
        $this->normalizeStatuses();
    }

    private function normalizeStatuses(): void
    {
        if (in_array('open', $this->statuses)) {
            $this->groupStatuses[] = 'open';
        }

        if (in_array('finished', $this->statuses)) {
            $this->groupStatuses[] = 'finished';
        }

        $this->groupStatuses = array_unique($this->groupStatuses);

        if (in_array('open', $this->groupStatuses)) {
            $this->statuses = array_diff($this->statuses, ['open'], Entity\Ticket::OPEN_STATUSES);
        }

        if (in_array('finished', $this->groupStatuses)) {
            $this->statuses = array_diff($this->statuses, ['finished'], Entity\Ticket::FINISHED_STATUSES);
        }

        $this->statuses = array_unique($this->statuses);
    }

    /**
     * @return Collections\Collection<int, Entity\User>
     */
    public function getInvolves(): Collections\Collection
    {
        return $this->involves;
    }

    /**
     * @param Collections\Collection<int, Entity\User> $values
     */
    public function setInvolves(Collections\Collection $values): void
    {
        $this->involves = $values;
    }

    /**
     * @return Collections\Collection<int, Entity\User>
     */
    public function getAssignees(): Collections\Collection
    {
        return $this->assignees;
    }

    /**
     * @param Collections\Collection<int, Entity\User> $values
     */
    public function setAssignees(Collections\Collection $values): void
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

    /**
     * @return Collections\Collection<int, Entity\User>
     */
    public function getRequesters(): Collections\Collection
    {
        return $this->requesters;
    }

    /**
     * @param Collections\Collection<int, Entity\User> $values
     */
    public function setRequesters(Collections\Collection $values): void
    {
        $this->requesters = $values;
    }

    /**
     * @return Collections\Collection<int, Entity\Label>
     */
    public function getLabels(): Collections\Collection
    {
        return $this->labels;
    }

    public function addLabel(Entity\Label $label): void
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }
    }

    public function removeLabel(Entity\Label $label): void
    {
        $this->labels->removeElement($label);
    }

    /**
     * @return string[]
     */
    public function getPriorities(): array
    {
        return $this->priorities;
    }

    /**
     * @param string[] $values
     */
    public function setPriorities(array $values): void
    {
        $this->priorities = $values;
    }

    /**
     * @return string[]
     */
    public function getUrgencies(): array
    {
        return $this->urgencies;
    }

    /**
     * @param string[] $values
     */
    public function setUrgencies(array $values): void
    {
        $this->urgencies = $values;
    }

    /**
     * @return string[]
     */
    public function getImpacts(): array
    {
        return $this->impacts;
    }

    /**
     * @param string[] $values
     */
    public function setImpacts(array $values): void
    {
        $this->impacts = $values;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $value): void
    {
        $this->type = $value;
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

        if (!$this->involves->isEmpty()) {
            $values = $this->processActorValues($this->involves);
            $textualQueryParts[] = "involves:{$values}";
        }

        if (!$this->assignees->isEmpty()) {
            $values = $this->processActorValues($this->assignees);
            $textualQueryParts[] = "assignee:{$values}";
        }

        if ($this->unassignedOnly) {
            $textualQueryParts[] = "no:assignee";
        }

        if (!$this->requesters->isEmpty()) {
            $values = $this->processActorValues($this->requesters);
            $textualQueryParts[] = "requester:{$values}";
        }

        foreach ($this->labels as $label) {
            $value = $label->getName();

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

    /**
     * @param Collections\Collection<int, Entity\User> $actors
     */
    private function processActorValues(Collections\Collection $actors): string
    {
        $actorIds = array_map(function (Entity\User $user): string {
            return "#{$user->getId()}";
        }, $actors->toArray());

        return implode(',', $actorIds);
    }
}
