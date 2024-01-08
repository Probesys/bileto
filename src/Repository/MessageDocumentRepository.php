<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\MessageDocument;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageDocument>
 *
 * @method MessageDocument|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageDocument|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageDocument[]    findAll()
 * @method MessageDocument[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageDocumentRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageDocument::class);
    }

    public function save(MessageDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param MessageDocument[] $entities
     */
    public function saveBatch(array $entities, bool $flush = false): void
    {
        foreach ($entities as $entity) {
            $this->save($entity, false);
        }

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MessageDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
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
