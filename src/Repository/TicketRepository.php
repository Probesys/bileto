<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Ticket;
use App\Entity\User;
use App\SearchEngine;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use App\Utils\Pagination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM;
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
 *
 * @phpstan-import-type PaginationOptions from Pagination
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
     * @param SearchEngine\Query[] $queries
     * @param array{string, 'ASC'|'DESC'} $sort
     * @param PaginationOptions $paginationOptions
     *
     * @return Pagination<Ticket>
     */
    public function findByQueries(array $queries, array $sort, array $paginationOptions): Pagination
    {
        $qb = $this->createSearchQueryBuilder($queries);
        $qb->orderBy("t.{$sort[0]}", $sort[1]);

        /** @var Pagination<Ticket> */
        $pagination = Pagination::paginate($qb->getQuery(), $paginationOptions);

        return $pagination;
    }

    /**
     * @param SearchEngine\Query[] $queries
     */
    public function countByQueries(array $queries): int
    {
        $qb = $this->createSearchQueryBuilder($queries);
        $qb->select($qb->expr()->count('t.id'));
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param SearchEngine\Query[] $queries
     */
    private function createSearchQueryBuilder(array $queries): ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('t');

        if ($this->mustIncludeContracts($queries)) {
            $qb->leftJoin('t.contracts', 'c');
        }

        foreach ($queries as $sequence => $query) {
            list($whereQuery, $parameters) = $this->ticketQueryBuilder->build($query, $sequence);

            $qb->andWhere($whereQuery);

            foreach ($parameters as $key => $value) {
                $qb->setParameter($key, $value);
            }
        }

        return $qb;
    }

    /**
     * @param SearchEngine\Query[] $queries
     */
    private function mustIncludeContracts(array $queries): bool
    {
        foreach ($queries as $query) {
            foreach ($query->getConditions() as $condition) {
                if ($condition->isQualifierCondition() && $condition->getQualifier() === 'contract') {
                    return true;
                }
            }
        }

        return false;
    }
}
