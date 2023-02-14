<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
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
class TicketRepository extends ServiceEntityRepository implements UidGeneratorInterface
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
     * @param array<string, int[]> $orgaFilters
     * @param array<string, mixed> $criteria
     * @param string[] $sort
     * @return Ticket[]
     */
    public function findBySearch(User $actor, array $orgaFilters, array $criteria, array $sort): array
    {
        $qb = $this->createSearchQueryBuilder($actor, $orgaFilters, $criteria);
        $qb->orderBy("t.{$sort[0]}", $sort[1]);

        $query = $qb->getQuery();
        return $query->getResult();
    }

    /**
     * @param array<string, int[]> $orgaFilters
     * @param array<string, mixed> $criteria
     */
    public function countBySearch(User $actor, array $orgaFilters, array $criteria): int
    {
        $qb = $this->createSearchQueryBuilder($actor, $orgaFilters, $criteria);
        $qb->select($qb->expr()->count('t.id'));

        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    /**
     * @param array<string, int[]> $orgaFilters
     * @param array<string, mixed> $criteria
     */
    private function createSearchQueryBuilder(User $actor, array $orgaFilters, array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t');

        if (!empty($orgaFilters['all'])) {
            $qb->where(
                $qb->expr()->in('t.organization', ':orgaAll'),
            );
            $qb->setParameter('orgaAll', $orgaFilters['all']);
        }

        $actorExpr = $qb->expr()->orX(
            $qb->expr()->eq('t.createdBy', ':actor'),
            $qb->expr()->eq('t.requester', ':actor'),
            $qb->expr()->eq('t.assignee', ':actor'),
        );

        if (!empty($orgaFilters['actor'])) {
            foreach ($orgaFilters['actor'] as $key => $orgaId) {
                $qb->orWhere($qb->expr()->andX(
                    $qb->expr()->eq('t.organization', ":orga{$key}"),
                    $actorExpr,
                ));
                $qb->setParameter("orga{$key}", $orgaId);
            }

            $qb->setParameter('actor', $actor->getId());
        }

        if (empty($orgaFilters['all']) && empty($orgaFilters['actor'])) {
            // Make sure to restrain the tickets list to those available to the
            // given user.
            // In normal cases, there should always be an orgaFilter applied,
            // or at least a constraint on another actor field, so this
            // condition should never match. But we're never too sure.
            $qb->where($actorExpr);
            $qb->setParameter('actor', $actor->getId());
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
