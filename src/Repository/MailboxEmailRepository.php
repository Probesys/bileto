<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\MailboxEmail;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MailboxEmail>
 *
 * @method MailboxEmail|null find($id, $lockMode = null, $lockVersion = null)
 * @method MailboxEmail|null findOneBy(array $criteria, array $orderBy = null)
 * @method MailboxEmail[]    findAll()
 * @method MailboxEmail[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
