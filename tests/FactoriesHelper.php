<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

trait FactoriesHelper
{
    /**
     * Sometimes, we need to clear the entities or they will stay in memory.
     * This is particularly important when we create entities with relations
     * and that we try to delete them in a controller. Doctrine complains about
     * the relations then.
     *
     * An alternative would be to set `cascade: ['remove']` on the relations,
     * but it would decrease the performance for no interest since we don't
     * need it outside of the tests.
     */
    public function clearEntityManager(): void
    {
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $doctrine = static::getContainer()->get('doctrine');
        $entityManager = $doctrine->getManager();
        $entityManager->clear();
    }
}
