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
use Doctrine\ORM\Query\Expr;

/**
 * @extends ServiceEntityRepository<Ticket>
 *
 * @method Ticket|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ticket|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ticket[]    findAll()
 * @method Ticket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @method Ticket findOneOrCreateBy(array $criteria, array $valuesToCreate = [], bool $flush = false)
 */
class TicketRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;
    use FindOrCreateTrait;

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
     * @param array<array<string|int,mixed>> $criteria
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
     * @param array<array<string|int,mixed>> $criteria
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
     * @param array<array<string|int,mixed>> $criteria
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

        foreach ($criteria as $criterion) {
            if (count($criterion) === 1) {
                /** @var string $field */
                $field = array_key_first($criterion);
                $condition = $criterion[$field];
                $expr = $this->buildCriteriaExpr($qb, $field, $condition);
            } else {
                $expr = $this->buildOrExpr($qb, $criterion);
            }

            $qb->andWhere($expr);
        }

        return $qb;
    }

    /**
     * return Expr\Comparison|Expr\Func|string
     */
    private function buildCriteriaExpr(QueryBuilder $qb, string $field, mixed $condition): mixed
    {
        if ($condition === null) {
            return $qb->expr()->isNull("t.{$field}");
        } elseif (is_array($condition)) {
            $qb->setParameter($field, $condition);
            return $qb->expr()->in("t.{$field}", ":{$field}");
        } else {
            $qb->setParameter($field, $condition);
            return $qb->expr()->eq("t.{$field}", ":{$field}");
        }
    }

    /**
     * @param array<array<string|int,mixed>> $criteria
     */
    private function buildOrExpr(QueryBuilder $qb, array $criteria): Expr\Orx
    {
        $expressions = [];
        foreach ($criteria as $criterion) {
            /** @var string $field */
            $field = array_key_first($criterion);
            $condition = $criterion[$field];
            $expressions[] = $this->buildCriteriaExpr($qb, $field, $condition);
        }

        return $qb->expr()->orX(...$expressions);
    }
}
