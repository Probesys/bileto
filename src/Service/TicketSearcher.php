<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Repository\TicketRepository;
use Symfony\Bundle\SecurityBundle\Security;

class TicketSearcher
{
    private TicketRepository $ticketRepository;

    /** @var array<string,mixed> $criteria */
    private array $criteria = [];

    /** @var array<string,int[]> $orgaFilters */
    private array $orgaFilters = [
        'all' => [],
        'actor' => [],
    ];

    private Security $security;

    public function __construct(TicketRepository $ticketRepository, Security $security)
    {
        $this->ticketRepository = $ticketRepository;
        $this->security = $security;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->orgaFilters['all'] = [];
        $this->orgaFilters['actor'] = [];

        $this->addOrgaFilter($organization);

        return $this;
    }

    /**
     * @param Organization[] $organizations
     */
    public function setOrganizations(array $organizations): self
    {
        $this->orgaFilters['all'] = [];
        $this->orgaFilters['actor'] = [];

        foreach ($organizations as $organization) {
            $this->addOrgaFilter($organization);
        }

        return $this;
    }

    private function addOrgaFilter(Organization $organization): void
    {
        if ($this->security->isGranted('orga:see:tickets:all', $organization)) {
            $this->orgaFilters['all'][] = $organization->getId();
        } else {
            $this->orgaFilters['actor'][] = $organization->getId();
        }
    }

    public function setCriteria(string $property, mixed $criteria): self
    {
        $this->criteria[$property] = $criteria;

        return $this;
    }

    /**
     * @return \App\Entity\Ticket[]
     */
    public function getTickets(): array
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $sort = ['createdAt', 'DESC'];
        return $this->ticketRepository->findBySearch($user, $this->orgaFilters, $this->criteria, $sort);
    }

    public function countToAssign(): int
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $criteria = array_merge($this->criteria, [
            'assignee' => null,
        ]);
        return $this->ticketRepository->countBySearch($user, $this->orgaFilters, $criteria);
    }

    public function countAssignedTo(User $assignee): int
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $criteria = array_merge($this->criteria, [
            'assignee' => $assignee->getId(),
        ]);
        return $this->ticketRepository->countBySearch($user, $this->orgaFilters, $criteria);
    }
}
