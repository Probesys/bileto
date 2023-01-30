<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Ticket>
 *
 * @method Ticket|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ticket|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ticket[]    findAll()
 * @method Ticket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TicketRepository extends ServiceEntityRepository
{
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function save(Ticket $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Ticket $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param array<string, mixed> $criteria
     * @param string[] $sort
     * @return Ticket[]
     */
    public function findBySearch(array $criteria, array $sort): array
    {
        $qb = $this->createSearchQueryBuilder($criteria);
        $qb->orderBy("t.{$sort[0]}", $sort[1]);

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function countBySearch(array $criteria): int
    {
        $qb = $this->createSearchQueryBuilder($criteria);
        $qb->select($qb->expr()->count('t.id'));

        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function createSearchQueryBuilder(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t');

        if (isset($criteria['actor'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->eq('t.createdBy', ':actor'),
                $qb->expr()->eq('t.requester', ':actor'),
                $qb->expr()->eq('t.assignee', ':actor'),
            ));
            $qb->setParameter('actor', $criteria['actor']);
            unset($criteria['actor']);
        }

        foreach ($criteria as $field => $condition) {
            if ($condition === null) {
                $expr = $qb->expr()->isNull("t.{$field}");
            } elseif (is_array($condition)) {
                $expr = $qb->expr()->in("t.{$field}", ":{$field}");
                $qb->setParameter($field, $condition);
            } else {
                $expr = $qb->expr()->eq("t.{$field}", ":{$field}");
                $qb->setParameter($field, $condition);
            }

            $qb->andWhere($expr);
        }

        return $qb;
    }
}
