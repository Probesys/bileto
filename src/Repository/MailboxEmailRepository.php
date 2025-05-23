<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\MailboxEmail;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailboxEmail>
 */
class MailboxEmailRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<MailboxEmail> */
    use CommonTrait;
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailboxEmail::class);
    }

    /**
     * @return MailboxEmail[]
     */
    public function findInError(): array
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT me
            FROM App\Entity\MailboxEmail me
            WHERE me.lastErrorAt IS NOT NULL
            ORDER BY me.createdAt DESC
        SQL);

        return $query->getResult();
    }
}
