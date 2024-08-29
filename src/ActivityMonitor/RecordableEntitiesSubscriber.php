<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\ActivityMonitor;

use App\Entity;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Monitor the insertions, updates and deletions of entities and save them as
 * EntityEvents. Only entities implementing the RecordableEntityInterface are
 * monitored.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::onFlush)]
class RecordableEntitiesSubscriber
{
    /**
     * Save the insertions of the RecordableEntityInterface entities as
     * EntityEvents.
     *
     * @param LifecycleEventArgs<EntityManager> $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof RecordableEntityInterface) {
            $entityEvent = Entity\EntityEvent::initInsert($entity);

            $entityManager = $args->getObjectManager();
            $entityManager->persist($entityEvent);
            $entityManager->flush();
        }
    }

    /**
     * Save the updates of the RecordableEntityInterface entities as
     * EntityEvents.
     *
     * @param LifecycleEventArgs<EntityManager> $args
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!($entity instanceof RecordableEntityInterface)) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entityChanges = array_merge(
            $this->processEntityChangeSet($entity, $unitOfWork),
            $this->processEntityCollectionUpdates($entity, $unitOfWork),
        );

        if ($entityChanges) {
            $entityEvent = Entity\EntityEvent::initUpdate($entity, $entityChanges);

            $entityManager->persist($entityEvent);
            $entityManager->flush();
        }
    }

    /**
     * Save the deletions of the RecordableEntityInterface entities as
     * EntityEvents.
     */
    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entityEvents = [];

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if (!($entity instanceof RecordableEntityInterface)) {
                continue;
            }

            $entityEvents[] = Entity\EntityEvent::initDelete($entity);
        }

        // Save all the entity events in the database.
        foreach ($entityEvents as $entityEvent) {
            $entityManager->persist($entityEvent);
            // This is required by Doctrine in the onFlush event. It likely
            // acts as flushing during a flush :).
            $classMetadata = $entityManager->getClassMetadata(Entity\EntityEvent::class);
            $unitOfWork->computeChangeSet($classMetadata, $entityEvent);
        }
    }

    /**
     * Return a list of fields changes.
     *
     * The returned array is indexed by the changed fields and the values are
     * the changes. A change is an array containing two elements: the old and
     * the new values of the field.
     *
     * @return array<string, array{mixed, mixed}>
     */
    private function processEntityChangeSet(RecordableEntityInterface $entity, UnitOfWork $unitOfWork): array
    {
        $processedChanges = [];

        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        foreach ($changeSet as $field => $fieldChanges) {
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

    /**
     * Return a list of changes concerning the ManyToMany relations.
     *
     * The returned array is indexed by the changed fields and the values are
     * the changes. A change is an array containing two arrays:
     *
     * - the first array contains the removed relation's ids
     * - the second array contains the added relation's ids
     *
     * Important note: this method heavily relies on internal methods of
     * Doctrine. It may break when updating to a patch or minor version of
     * Doctrine. However, I wasn't able to find a better solution, except by
     * recording the changes manually, which became really annoying.
     *
     * @return array<string, array{mixed, mixed}>
     */
    private function processEntityCollectionUpdates(
        RecordableEntityInterface $entity,
        UnitOfWork $unitOfWork,
    ): array {
        $processedChanges = [];

        $collectionUpdates = $unitOfWork->getScheduledCollectionUpdates();

        foreach ($collectionUpdates as $collectionUpdate) {
            $owner = $collectionUpdate->getOwner();

            $concernsEntity = (
                $owner &&
                $owner instanceof RecordableEntityInterface &&
                $owner::class === $entity::class &&
                $owner->getId() === $entity->getId()
            );

            if ($concernsEntity) {
                $field = $collectionUpdate->getMapping()->fieldName;

                $processedChanges[$field] = [
                    $this->processChangesValue($collectionUpdate->getDeleteDiff()),
                    $this->processChangesValue($collectionUpdate->getInsertDiff()),
                ];
            }
        }

        // Note that we don't iterate over $unitOfWork->getScheduledCollectionDeletions().
        // In fact, it (seems to) always returns an empty list during Doctrine
        // postUpdate event.
        // It does however return collectionDeletions during Doctrine onFlush
        // event when emptying a many-to-many relation with a Symfony Form
        // using an EntityType field. Unfortunately, these collections don't
        // hold the information of what have been removed and so we cannot
        // build our "update" EntityEvent correctly.
        //
        // In fact, to correctly record many-to-many relations, you MUST set
        // `by_reference: false` when configuring a form field corresponding to
        // a many-to-many relation (see the observers field of
        // `\App\Form\Ticket\ActorsForm`). With this, the changes are visible
        // in the collectionUpdates (either on postUpdate or onFlush events).
        //
        // See https://stackoverflow.com/a/15394241

        return $processedChanges;
    }

    /**
     * Return a stringifiable representation of the given value.
     */
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
        } elseif (is_array($value)) {
            return array_map(function ($valueItem) {
                return $this->processChangesValue($valueItem);
            }, $value);
        } else {
            return $value;
        }
    }
}
