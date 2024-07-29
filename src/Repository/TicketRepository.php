<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Ticket;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use App\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Ticket> */
    use CommonTrait;
    use UidGeneratorTrait;
    /** @phpstan-use FindOrCreateTrait<Ticket> */
    use FindOrCreateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findOneByUidWithAssociations(string $uid): ?Ticket
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT
                t,
                t_createdBy,
                t_updatedBy,
                t_requester,
                t_assignee,
                t_team,
                t_organization,
                t_messages,
                m_documents,
                t_solution,
                t_contracts,
                t_timeSpents,
                t_labels
            FROM App\Entity\Ticket t
            LEFT JOIN t.createdBy t_createdBy
            LEFT JOIN t.updatedBy t_updatedBy
            LEFT JOIN t.requester t_requester
            LEFT JOIN t.assignee t_assignee
            LEFT JOIN t.team t_team
            LEFT JOIN t.organization t_organization
            LEFT JOIN t.messages t_messages
            LEFT JOIN t_messages.messageDocuments m_documents
            LEFT JOIN t.solution t_solution
            LEFT JOIN t.contracts t_contracts
            LEFT JOIN t.timeSpents t_timeSpents
            LEFT JOIN t.labels t_labels
            WHERE t.uid = :uid
        SQL);

        $query->setParameter('uid', $uid);

        return $query->getOneOrNullResult();
    }

    /**
     * @return Ticket[]
     */
    public function findAssociableTickets(Contract $contract): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT t
            FROM App\Entity\Ticket t

            WHERE t.organization = :organization
            AND :startAt <= t.createdAt
            AND t.createdAt < :endAt
        SQL);

        $query->setParameter('organization', $contract->getOrganization());
        $query->setParameter('startAt', $contract->getStartAt());
        $query->setParameter('endAt', $contract->getEndAt());

        $tickets = $query->getResult();

        // Filter tickets that have no ongoing contract on the period of the
        // given contract.
        return array_filter($tickets, function ($ticket) use ($contract): bool {
            $ticketContracts = $ticket->getContracts()->toArray();

            $hasOngoingContract = Utils\ArrayHelper::any(
                $ticketContracts,
                function ($ticketContract) use ($contract): bool {
                    return (
                        $ticketContract->getEndAt() >= $contract->getStartAt() &&
                        $ticketContract->getStartAt() < $contract->getEndAt()
                    );
                }
            );

            return !$hasOngoingContract;
        });
    }
}
