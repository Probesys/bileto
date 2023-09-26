<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\EventSubscriber;

use App\Entity\EntityEvent;
use App\Entity\ActivityRecordableInterface;
use App\Repository\EntityEventRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
class EntityActivitySubscriber
{
    private EntityEventRepository $entityEventRepository;

    private ?EntityEvent $entityEventRemove = null;

    public function __construct(EntityEventRepository $entityEventRepository)
    {
        $this->entityEventRepository = $entityEventRepository;
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof ActivityRecordableInterface)) {
            return;
        }

        $entityEvent = EntityEvent::initInsert($entity);
        $this->entityEventRepository->save($entityEvent, true);
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof ActivityRecordableInterface)) {
            return;
        }

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $entityChanges = $unitOfWork->getEntityChangeSet($entity);
        $entityChanges = $this->processChanges($entityChanges);

        if ($entityChanges) {
            $entityEvent = EntityEvent::initUpdate($entity, $entityChanges);
            $this->entityEventRepository->save($entityEvent, true);
        }
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof ActivityRecordableInterface)) {
            return;
        }

        // We need to initialize the EntityEvent in preRemove callback because
        // the entity id will always be null in postRemove.
        $this->entityEventRemove = EntityEvent::initDelete($entity);
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        if (!$this->entityEventRemove) {
            return;
        }

        $this->entityEventRepository->save($this->entityEventRemove, true);
        $this->entityEventRemove = null;
    }

    /**
     * @param array<string, array<int, mixed>|PersistentCollection<int, object>> $changes
     *
     * @return array<string, array<int, mixed>>
     */
    private function processChanges(array $changes): array
    {
        $processedChanges = [];

        foreach ($changes as $field => $fieldChanges) {
            if ($fieldChanges instanceof PersistentCollection) {
                // If it is a PersistentCollection (i.e. an Entity relation),
                // it means that another Entity has been changed or created at
                // the same time than the current change. We don't need to
                // record this one.
                continue;
            }

            if ($field === 'updatedAt' || $field === 'updatedBy') {
                // We don't want to track these fields since they are similar
                // to the EntityEvent createdAt and createdBy fields. Also,
                // they would appear in the tickets timeline, something that we
                // don't want.
                continue;
            }

            $processedChanges[$field] = [
                $this->processChangesValue($fieldChanges[0]),
                $this->processChangesValue($fieldChanges[1]),
            ];
        }

        return $processedChanges;
    }

    private function processChangesValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        } elseif (is_object($value)) {
            if (!is_callable([$value, 'getId'])) {
                $class = $value::class;
                throw new \LogicException("{$class} must implement getId()");
            }

            return $value->getId();
        } else {
            return $value;
        }
    }
}
