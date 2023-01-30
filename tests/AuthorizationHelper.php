<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

use App\Entity\Authorization;
use App\Entity\Organization;
use App\Entity\Role;
use App\Entity\User;
use App\Utils\Random;
use App\Utils\Time;
use Doctrine\ORM\EntityManager;

trait AuthorizationHelper
{
    /**
     * @param string[] $permissions
     */
    public function grantAdmin(User $user, array $permissions): void
    {
        if (empty($permissions)) {
            return;
        }

        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var \App\Repository\RoleRepository $roleRepo */
        $roleRepo = $entityManager->getRepository(Role::class);
        /** @var \App\Repository\AuthorizationRepository $authorizationRepo */
        $authorizationRepo = $entityManager->getRepository(Authorization::class);

        $superPermissionGranted = in_array('admin:*', $permissions);
        if ($superPermissionGranted) {
            $role = $roleRepo->findOrCreateSuperRole();
            $authorizationRepo->grant($user, $role);
        } else {
            $permissions = Role::sanitizePermissions('admin', $permissions);

            $role = new Role();
            $role->setUid($roleRepo->generateUid());
            $role->setCreatedAt(Time::now());
            $role->setName(Random::hex(10));
            $role->setDescription('The role description');
            $role->setType('admin');
            $role->setPermissions($permissions);

            $roleRepo->save($role);
            $authorizationRepo->grant($user, $role);
        }
    }

    /**
     * @param string[] $permissions
     */
    public function grantOrga(User $user, array $permissions, ?Organization $organization = null): void
    {
        if (empty($permissions)) {
            return;
        }

        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry $registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var \App\Repository\RoleRepository $roleRepo */
        $roleRepo = $entityManager->getRepository(Role::class);
        /** @var \App\Repository\AuthorizationRepository $authorizationRepo */
        $authorizationRepo = $entityManager->getRepository(Authorization::class);

        $permissions = Role::sanitizePermissions('orga', $permissions);

        $role = new Role();
        $role->setUid($roleRepo->generateUid());
        $role->setCreatedAt(Time::now());
        $role->setName(Random::hex(10));
        $role->setDescription('The role description');
        $role->setType('orga');
        $role->setPermissions($permissions);

        $roleRepo->save($role);
        $authorizationRepo->grant($user, $role, $organization);
    }
}
