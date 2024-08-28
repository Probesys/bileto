<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\TicketActivity;

use App\Entity;
use App\Repository;
use App\Security;
use App\Service;
use App\Utils;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handle post-actions on ticket's transfers.
 **/
class TransfersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TicketEvent::TRANSFERRED => ['processTransfer', 100],
        ];
    }

    public function __construct(
        private Repository\TicketRepository $ticketRepository,
        private Repository\TimeSpentRepository $timeSpentRepository,
        private Repository\TeamRepository $teamRepository,
        private Service\ContractTimeAccounting $contractTimeAccounting,
        private Security\Authorizer $authorizer,
    ) {
    }

    /**
     * Make sure that the ticket's actors and contracts are available in the
     * new ticket's organization.
     */
    public function processTransfer(TicketEvent $event): void
    {
        $ticket = $event->getTicket();
        $organization = $ticket->getOrganization();

        $team = $ticket->getTeam();
        if ($team && !$this->accessIsGrantedToTeam($team, $organization)) {
            $ticket->setTeam(null);
        }

        $assignee = $ticket->getAssignee();
        if ($assignee && !$this->authorizer->isGrantedToUser($assignee, 'orga:see', $organization)) {
            $ticket->setAssignee(null);
        }

        foreach ($ticket->getObservers() as $observer) {
            if (!$this->authorizer->isGrantedToUser($observer, 'orga:see', $organization)) {
                $ticket->removeObserver($observer);
            }
        }

        foreach ($organization->getObservers() as $observer) {
            $ticket->addObserver($observer);
        }

        foreach ($ticket->getContracts() as $contract) {
            if (!$this->isContractInOrganization($contract, $organization)) {
                $ticket->removeContract($contract);
            }
        }

        $unaccountedTimeSpents = [];
        foreach ($ticket->getTimeSpents() as $timeSpent) {
            $contract = $timeSpent->getContract();

            if (!$contract) {
                continue;
            }

            if (!$this->isContractInOrganization($contract, $organization)) {
                $unaccountedTimeSpents[] = $timeSpent;
            }
        }

        $this->contractTimeAccounting->unaccountTimeSpents($unaccountedTimeSpents);

        $this->timeSpentRepository->save($unaccountedTimeSpents, true);
        $this->ticketRepository->save($ticket, true);
    }

    private function accessIsGrantedToTeam(Entity\Team $team, Entity\Organization $organization): bool
    {
        $availableTeams = $this->teamRepository->findByOrganization($organization);

        return Utils\ArrayHelper::any($availableTeams, function ($availableTeam) use ($team): bool {
            return $availableTeam->getId() === $team->getId();
        });
    }

    private function isContractInOrganization(Entity\Contract $contract, Entity\Organization $organization): bool
    {
        $contractOrganization = $contract->getOrganization();
        return $contractOrganization->getId() === $organization->getId();
    }
}
