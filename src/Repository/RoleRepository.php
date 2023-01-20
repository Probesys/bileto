<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Repository;

use App\Entity\Role;
use App\Utils\Time;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @extends ServiceEntityRepository<Role>
 *
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoleRepository extends ServiceEntityRepository
{
    use UidGeneratorTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function save(Role $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Role $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOrCreateSuperRole(): Role
    {
        $superRole = $this->findOneBy(['type' => 'super']);
        if ($superRole) {
            return $superRole;
        }

        $superRole = new Role();
        $superRole->setUid($this->generateUid());
        $superRole->setCreatedAt(Time::now());
        $superRole->setName(new TranslatableMessage('Super-admin'));
        $superRole->setDescription(
            new TranslatableMessage('A special admin role with an access to everything (cannot be deleted).')
        );
        $superRole->setType('super');
        $superRole->setPermissions(['admin:*']);

        $this->save($superRole, true);

        return $superRole;
    }
}
