<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

use App\Entity\Authorization;
use App\Entity\Organization;
use App\Entity\Role;
use App\Entity\Team;
use App\Entity\User;
use App\Security;
use App\Service;
use App\Utils;
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
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var \App\Repository\RoleRepository */
        $roleRepo = $entityManager->getRepository(Role::class);

        /** @var Security\Authorizer */
        $authorizer = $container->get(Security\Authorizer::class);

        $superPermissionGranted = in_array('admin:*', $permissions);
        if ($superPermissionGranted) {
            $role = $roleRepo->findOrCreateSuperRole();
            $authorizer->grant($user, $role);
        } else {
            $role = new Role();
            $role->setName(Utils\Random::hex(10));
            $role->setDescription('The role description');
            $role->setType('admin');
            $role->setPermissions($permissions);

            $roleRepo->save($role);
            $authorizer->grant($user, $role);
        }
    }

    /**
     * @param string[] $permissions
     * @param 'user'|'agent' $type
     */
    public function grantOrga(
        User $user,
        array $permissions,
        ?Organization $organization = null,
        string $type = 'agent',
    ): void {
        if (empty($permissions)) {
            return;
        }

        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var \App\Repository\RoleRepository */
        $roleRepo = $entityManager->getRepository(Role::class);

        /** @var Security\Authorizer */
        $authorizer = $container->get(Security\Authorizer::class);

        $role = new Role();
        $role->setName(Utils\Random::hex(10));
        $role->setDescription('The role description');
        $role->setType($type);
        $role->setPermissions($permissions);

        $roleRepo->save($role);
        $authorizer->grant($user, $role, $organization);
    }

    /**
     * @param string[] $permissions
     */
    public function grantTeam(
        Team $team,
        array $permissions,
        ?Organization $organization = null,
    ): void {
        if (empty($permissions)) {
            return;
        }

        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var \App\Repository\RoleRepository */
        $roleRepo = $entityManager->getRepository(Role::class);
        /** @var Service\TeamService */
        $teamService = $container->get(Service\TeamService::class);

        $role = new Role();
        $role->setName(Utils\Random::hex(10));
        $role->setDescription('The role description');
        $role->setType('agent');
        $role->setPermissions($permissions);

        $roleRepo->save($role);
        $teamService->createAuthorization($team, $role, $organization);
    }
}
