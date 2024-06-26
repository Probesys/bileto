<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Message;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 *
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @method Message findOneOrCreateBy(array $criteria, array $valuesToCreate = [], bool $flush = false)
 */
class MessageRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Message> */
    use CommonTrait;
    use UidGeneratorTrait;
    /** @phpstan-use FindOrCreateTrait<Message> */
    use FindOrCreateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }
}
