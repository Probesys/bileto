<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\MessageDocument;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageDocument>
 */
class MessageDocumentRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<MessageDocument> */
    use CommonTrait;
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageDocument::class);
    }

    public function countByHash(string $hash): int
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            SELECT COUNT(md)
            FROM App\Entity\MessageDocument md
            WHERE md.hash = :hash
        SQL);
        $query->setParameter('hash', $hash);

        return (int) $query->getSingleScalarResult();
    }
}
