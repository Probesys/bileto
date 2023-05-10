<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\EntityListener;

use App\Entity\MetaEntityInterface;
use App\Repository\UidGeneratorInterface;
use App\Utils\Time;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener]
class EntitySetMetaListener
{
    private EntityManagerInterface $entityManager;

    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function prePersist(MetaEntityInterface $entity): void
    {
        if (!$entity->getUid()) {
            /** @var UidGeneratorInterface $entityRepository */
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

        /** @var ?\App\Entity\User $currentUser */
        $currentUser = $this->security->getUser();
        $now = Time::now();

        if (!$entity->getCreatedAt()) {
            $entity->setCreatedAt($now);
        }

        if (!$entity->getCreatedBy()) {
            $entity->setCreatedBy($currentUser);
        }

        $entity->setUpdatedAt($now);
        $entity->setUpdatedBy($currentUser);
    }

    public function preUpdate(MetaEntityInterface $entity): void
    {
        /** @var ?\App\Entity\User $currentUser */
        $currentUser = $this->security->getUser();
        $now = Time::now();

        $entity->setUpdatedAt($now);
        $entity->setUpdatedBy($currentUser);
    }
}
