<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\ActivityMonitor;

use App\Utils\Time;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;

/**
 * Monitor the entities changes to save the createdAt, createdBy, updatedAt and
 * updatedBy fields. Only entities implementing the TrackableEntityInterface
 * are monitored.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class TrackableEntitiesSubscriber
{
    public function __construct(
        private ActiveUser $activeUser,
    ) {
    }

    /**
     * Save all the tracking fields (i.e. createdAt, createdBy, updatedAt and
     * updatedBy) of the TrackableEntityInterface entities.
     *
     * createdAt and createdBy aren't changed if these fields are already set.
     * This allows to force a custom value for these fields.
     *
     * updatedAt and updatedBy are always set.
     *
     * createdBy and updatedBy are set to the user returned by ActiveUser::get().
     *
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof TrackableEntityInterface)) {
            return;
        }

        $activeUser = $this->activeUser->get();
        $now = Time::now();

        if (!$entity->getCreatedAt()) {
            $entity->setCreatedAt($now);
        }

        if (!$entity->getCreatedBy()) {
            $entity->setCreatedBy($activeUser);
        }

        $entity->setUpdatedAt($now);

        if ($activeUser !== null) {
            $entity->setUpdatedBy($activeUser);
        }
    }

    /**
     * Save the tracking fields updatedAt and updatedBy of the TrackableEntityInterface
     * entities.
     *
     * updatedBy is set to the user returned by ActiveUser::get().
     *
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof TrackableEntityInterface)) {
            return;
        }

        $activeUser = $this->activeUser->get();
        $now = Time::now();

        $entity->setUpdatedAt($now);

        if ($activeUser !== null) {
            $entity->setUpdatedBy($activeUser);
        }
    }
}
