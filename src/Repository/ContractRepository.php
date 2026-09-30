<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Contract;
use App\Entity\Organization;
use App\Entity\Ticket;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use App\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
 */
class ContractRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Contract> */
    use CommonTrait;
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    /**
     * @return ORM\Query<Contract>
     */
    public function findByOrganizationQuery(Organization $organization): ORM\Query
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT c
            FROM App\Entity\Contract c

            WHERE c.organization = :organization

            ORDER BY c.endAt DESC, c.name
        SQL);

        $query->setParameter('organization', $organization);

        return $query;
    }

    /**
     * @param Organization[] $organizations
     * @return ORM\Query<Contract>
     */
    public function findOngoingByOrganizationsQuery(array $organizations): ORM\Query
    {
        $entityManager = $this->getEntityManager();

        $now = Utils\Time::now();

        $query = $entityManager->createQuery(<<<SQL
            SELECT c
            FROM App\Entity\Contract c

            WHERE c.startAt <= :now
            AND :now <= c.endAt
            AND c.organization IN (:organizations)

            ORDER BY c.name
        SQL);

        $query->setParameter('now', $now);
        $query->setParameter('organizations', $organizations);

        return $query;
    }

    /**
     * @return Contract[]
     */
    public function findOngoingByOrganization(Organization $organization): array
    {
        $query = $this->findOngoingByOrganizationsQuery([$organization]);
        return $query->getResult();
    }

    /**
     * @return Contract[]
     */
    public function searchOngoingByOrganization(Organization $organization, string $search = '', int $limit = 10): array
    {
        $now = Utils\Time::now();

        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :organization')
            ->andWhere('c.startAt <= :now')
            ->andWhere(':now <= c.endAt')
            ->andWhere("LOWER(c.name) LIKE LOWER(:search) ESCAPE '!'")
            ->setParameter('organization', $organization)
            ->setParameter('now', $now)
            ->setParameter('search', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOngoingByIdAndOrganization(int $id, Organization $organization): ?Contract
    {
        $now = Utils\Time::now();

        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.organization = :organization')
            ->andWhere('c.startAt <= :now')
            ->andWhere(':now <= c.endAt')
            ->setParameter('id', $id)
            ->setParameter('organization', $organization)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPreferredForTicketContext(Ticket $ticket): ?Contract
    {
        if (!$ticket->getOrganization() || !$ticket->getRequester() || !$ticket->getAssignee()) {
            return null;
        }

        $now = Utils\Time::now();
        $query = $this->createQueryBuilder('c')
            ->innerJoin('c.tickets', 't')
            ->andWhere('t.organization = :organization')
            ->andWhere('c.organization = :organization')
            ->andWhere('t.requester = :requester')
            ->andWhere('t.assignee = :assignee')
            ->andWhere('t.id != :ticket')
            ->andWhere('c.startAt <= :now')
            ->andWhere(':now <= c.endAt')
            ->setParameter('organization', $ticket->getOrganization())
            ->setParameter('requester', $ticket->getRequester())
            ->setParameter('assignee', $ticket->getAssignee())
            ->setParameter('ticket', $ticket)
            ->setParameter('now', $now)
            ->orderBy('t.updatedAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults(1);

        return $query->getQuery()->getOneOrNullResult();
    }
}
