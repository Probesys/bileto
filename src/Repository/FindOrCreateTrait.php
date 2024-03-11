<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

/**
 * @template TEntity of object
 */
trait FindOrCreateTrait
{
    /**
     * @param array<string,mixed> $criteria
     * @param array<string,mixed> $valuesToBuild
     *
     * @return TEntity
     */
    public function findOneOrBuildBy(array $criteria, array $valuesToBuild = []): object
    {
        $entity = $this->findOneBy($criteria);
        if ($entity) {
            return $entity;
        }

        $values = array_merge($criteria, $valuesToBuild);

        $entityClassName = $this->getClassName();
        $entity = new $entityClassName();
        foreach ($values as $field => $value) {
            $setterMethod = 'set' . ucfirst($field);
            if (!is_callable([$entity, $setterMethod])) {
                throw new \BadMethodCallException("{$setterMethod} cannot be called on {$entityClassName}");
            }

            $entity->$setterMethod($value);
        }

        return $entity;
    }

    /**
     * @param array<string,mixed> $criteria
     * @param array<string,mixed> $valuesToCreate
     *
     * @return TEntity
     */
    public function findOneOrCreateBy(array $criteria, array $valuesToCreate = [], bool $flush = false): object
    {
        $entity = $this->findOneOrBuildBy($criteria, $valuesToCreate);

        $entityManager = $this->getEntityManager();

        if (!$entityManager->contains($entity)) {
            $entityManager->persist($entity);

            if ($flush) {
                $this->getEntityManager()->flush();
            }
        }

        return $entity;
    }
}
