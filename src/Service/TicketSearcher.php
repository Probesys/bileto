<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
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

    /**
     * @return \App\Entity\Ticket[]
     */
    public function getTickets(): array
    {
        $sort = ['createdAt' => 'DESC'];
        return $this->ticketRepository->findBy($this->criteria, $sort);
    }
}
