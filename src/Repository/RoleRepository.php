<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Role;
use App\Uid\UidGeneratorInterface;
use App\Uid\UidGeneratorTrait;
use App\Utils\Time;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository implements UidGeneratorInterface
{
    /** @phpstan-use CommonTrait<Role> */
    use CommonTrait;
    use UidGeneratorTrait;
    /** @phpstan-use FindOrCreateTrait<Role> */
    use FindOrCreateTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findOrCreateSuperRole(): Role
    {
        return $this->findOneOrCreateBy([
            'type' => 'super',
        ], [
            'name' => 'Super-admin',
            'description' => 'Super-admin',
            'permissions' => ['admin:*'],
        ], true);
    }

    public function findDefault(): ?Role
    {
        return $this->findOneBy([
            'type' => 'user',
            'isDefault' => true,
        ]);
    }

    public function unsetDefault(): void
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(<<<SQL
            UPDATE App\Entity\Role r
            SET r.isDefault = false
        SQL);

        $query->execute();
    }
}
