<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

/**
 * @template T of object
 */
trait CommonTrait
{
    /**
     * @param T|T[] $entities
     */
    public function save(mixed $entities, bool $flush = false): void
    {
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        $entityManager = $this->getEntityManager();

        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }

        if ($flush) {
            $entityManager->flush();
        }
    }

    /**
     * @param T|T[] $entities
     */
    public function remove(mixed $entities, bool $flush = false): void
    {
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        $entityManager = $this->getEntityManager();

        foreach ($entities as $entity) {
            $entityManager->remove($entity);
        }

        if ($flush) {
            $entityManager->flush();
        }
    }
}
