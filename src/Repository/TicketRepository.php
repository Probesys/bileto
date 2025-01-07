<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
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

    /**
     * @return Ticket[]
     */
    public function findResolvedOlderThan(\DateTimeImmutable $date): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT t
            FROM App\Entity\Ticket t

            WHERE t.status = 'resolved'
            AND t.statusChangedAt <= :date
        SQL);

        $query->setParameter('date', $date);

        return $query->getResult();
    }
}
