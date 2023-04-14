<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
use App\SearchEngine;
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

    private SearchEngine\QueryBuilder\TicketQueryBuilder $ticketQueryBuilder;

    public function __construct(
        ManagerRegistry $registry,
        SearchEngine\QueryBuilder\TicketQueryBuilder $ticketQueryBuilder
    ) {
        parent::__construct($registry, Ticket::class);
        $this->ticketQueryBuilder = $ticketQueryBuilder;
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
     * @param string[] $sort
     * @return Ticket[]
     */
    public function findByQuery(User $actor, array $orgaFilters, ?SearchEngine\Query $query, array $sort): array
    {
        $qb = $this->createSearchQueryBuilder($actor, $orgaFilters, $query);
        $qb->orderBy("t.{$sort[0]}", $sort[1]);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, int[]> $orgaFilters
     */
    public function countByQuery(User $actor, array $orgaFilters, ?SearchEngine\Query $query): int
    {
        $qb = $this->createSearchQueryBuilder($actor, $orgaFilters, $query);
        $qb->select($qb->expr()->count('t.id'));
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<string, int[]> $orgaFilters
     */
    private function createSearchQueryBuilder(User $actor, array $orgaFilters, ?SearchEngine\Query $query): QueryBuilder
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

        if ($query) {
            $this->ticketQueryBuilder->setCurrentUser($actor);
            list($whereQuery, $parameters) = $this->ticketQueryBuilder->build($query);

            $qb->andWhere($whereQuery);

            foreach ($parameters as $key => $value) {
                $qb->setParameter($key, $value);
            }
        }

        return $qb;
    }
}
