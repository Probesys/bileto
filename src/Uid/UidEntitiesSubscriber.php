<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Uid;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;

/**
 * Monitor the UidEntities to set their uid field before their insertion in the
 * database.
 */
#[AsDoctrineListener(event: Events::prePersist)]
class UidEntitiesSubscriber
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof UidEntityInterface)) {
            return;
        }

        if ($entity->getUid()) {
            return;
        }

        $entityRepository = $this->entityManager->getRepository($entity::class);

        if (!($entityRepository instanceof UidGeneratorInterface)) {
            $entityRepositoryClass = $entityRepository::class;
            $uidGeneratorInterfaceClass = UidGeneratorInterface::class;
            throw new \LogicException(
                "{$entityRepositoryClass} repository must implement {$uidGeneratorInterfaceClass}"
            );
        }

        $uid = $entityRepository->generateUid();
        $entity->setUid($uid);
    }
}
