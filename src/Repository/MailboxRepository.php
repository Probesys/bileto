<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Mailbox;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mailbox>
 *
 * @method Mailbox|null find($id, $lockMode = null, $lockVersion = null)
 * @method Mailbox|null findOneBy(array $criteria, array $orderBy = null)
 * @method Mailbox[]    findAll()
 * @method Mailbox[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MailboxRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mailbox::class);
    }

    public function save(Mailbox $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Mailbox $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}