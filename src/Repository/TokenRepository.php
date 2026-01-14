<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entity\Token>
 */
class TokenRepository extends ServiceEntityRepository
{
    /** @phpstan-use CommonTrait<Entity\Token> */
    use CommonTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entity\Token::class);
    }

    public function removeInvalidSince(\DateTimeInterface $date): int
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            DELETE App\Entity\Token t
            WHERE t.expiredAt <= :date
        SQL);

        $query->setParameter('date', $date);

        return $query->execute();
    }
}
