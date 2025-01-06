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

    public function findOneByNotificationReference(string $reference): ?Message
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT m
            FROM App\Entity\Message m
            WHERE JSON_CONTAINS(m.notificationsReferences, :reference) = true
        SQL);
        $query->setParameter('reference', '"' . $reference . '"');
        $query->setMaxResults(1);

        return $query->getOneOrNullResult();
    }
}
