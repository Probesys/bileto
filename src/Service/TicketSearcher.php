<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\TicketRepository;

class TicketSearcher
{
    private TicketRepository $ticketRepository;

    /** @var array<string,mixed> $criteria */
    private array $criteria = [];

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->criteria['organization'] = $organization->getId();

        return $this;
    }

    /**
     * @param array<string>|string $status
     */
    public function setStatus(array|string $status): self
    {
        $this->criteria['status'] = $status;

        return $this;
    }

    public function setAssignee(User|null $assignee): self
    {
        $this->criteria['assignee'] = $assignee ? $assignee->getId() : null;

        return $this;
    }

    /**
     * @return \App\Entity\Ticket[]
     */
    public function getTickets(): array
    {
        $sort = ['createdAt' => 'DESC'];
        return $this->ticketRepository->findBy($this->criteria, $sort);
    }

    public function countToAssign(): int
    {
        $criteria = array_merge($this->criteria, [
            'assignee' => null,
        ]);
        return $this->ticketRepository->count($criteria);
    }

    public function countAssignedTo(User $assignee): int
    {
        $criteria = array_merge($this->criteria, [
            'assignee' => $assignee->getId(),
        ]);
        return $this->ticketRepository->count($criteria);
    }
}
