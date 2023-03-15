<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Service;

use App\Entity\Organization;
use App\Entity\Ticket;
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
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $sort = ['createdAt', 'DESC'];

        return $this->ticketRepository->findBySearch(
            $currentUser,
            $this->orgaFilters,
            $this->criteria,
            $sort,
        );
    }

    /**
     * @return \App\Entity\Ticket[]
     */
    public function getTicketsOfCurrentUser(): array
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $criteria = [
            'status' => Ticket::OPEN_STATUSES,
            'assignee' => $currentUser,
        ];
        $sort = ['createdAt', 'DESC'];

        return $this->ticketRepository->findBySearch(
            $currentUser,
            $this->orgaFilters,
            $criteria,
            $sort,
        );
    }

    public function countTicketsOfCurrentUser(): int
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $criteria = [
            'status' => Ticket::OPEN_STATUSES,
            'assignee' => $currentUser,
        ];

        return $this->ticketRepository->countBySearch(
            $currentUser,
            $this->orgaFilters,
            $criteria,
        );
    }

    /**
     * @return \App\Entity\Ticket[]
     */
    public function getTicketsToAssign(): array
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $criteria = [
            'status' => Ticket::OPEN_STATUSES,
            'assignee' => null,
        ];
        $sort = ['createdAt', 'DESC'];

        return $this->ticketRepository->findBySearch(
            $currentUser,
            $this->orgaFilters,
            $criteria,
            $sort,
        );
    }

    public function countTicketsToAssign(): int
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $criteria = [
            'status' => Ticket::OPEN_STATUSES,
            'assignee' => null,
        ];

        return $this->ticketRepository->countBySearch(
            $currentUser,
            $this->orgaFilters,
            $criteria,
        );
    }
}
