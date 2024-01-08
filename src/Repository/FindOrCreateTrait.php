<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

trait FindOrCreateTrait
{
    /**
     * @param array<string,mixed> $criteria
     * @param array<string,mixed> $valuesToCreate
     */
    public function findOneOrCreateBy(array $criteria, array $valuesToCreate = [], bool $flush = false): object
    {
        $entity = $this->findOneBy($criteria);
        if ($entity) {
            return $entity;
        }

        $values = array_merge($criteria, $valuesToCreate);

        $entityClassName = $this->getClassName();
        $entity = new $entityClassName();
        foreach ($values as $field => $value) {
            $setterMethod = 'set' . ucfirst($field);
            if (!is_callable([$entity, $setterMethod])) {
                throw new \BadMethodCallException("{$setterMethod} cannot be called on {$entityClassName}");
            }

            $entity->$setterMethod($value);
        }

        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $entity;
    }
}
