<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Tests;

use App\Entity;
use App\Security;
use App\Service;
use App\Utils;
use Doctrine\ORM\EntityManager;

trait AuthorizationHelper
{
    /**
     * @param string[] $permissions
     */
    public function grantAdmin(Entity\User $user, array $permissions): void
    {
        if (empty($permissions)) {
            return;
        }

        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var \App\Repository\RoleRepository */
        $roleRepo = $entityManager->getRepository(Entity\Role::class);

        /** @var Security\Authorizer */
        $authorizer = $container->get(Security\Authorizer::class);

        $superPermissionGranted = in_array('admin:*', $permissions);
        if ($superPermissionGranted) {
            $role = $roleRepo->findOrCreateSuperRole();
            $authorizer->grant($user, $role);
        } else {
            $role = new Entity\Role();
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
        Entity\User $user,
        array $permissions,
        ?Entity\Organization $organization = null,
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
        $roleRepo = $entityManager->getRepository(Entity\Role::class);

        /** @var Security\Authorizer */
        $authorizer = $container->get(Security\Authorizer::class);

        $role = new Entity\Role();
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
        Entity\Team $team,
        array $permissions,
        ?Entity\Organization $organization = null,
    ): void {
        if (empty($permissions)) {
            return;
        }

        $container = static::getContainer();
        /** @var \Doctrine\Bundle\DoctrineBundle\Registry */
        $registry = $container->get('doctrine');
        $entityManager = $registry->getManager();

        /** @var \App\Repository\RoleRepository */
        $roleRepo = $entityManager->getRepository(Entity\Role::class);
        /** @var Service\TeamService */
        $teamService = $container->get(Service\TeamService::class);

        $role = new Entity\Role();
        $role->setName(Utils\Random::hex(10));
        $role->setDescription('The role description');
        $role->setType('agent');
        $role->setPermissions($permissions);

        $roleRepo->save($role);

        $teamAuthorization = new Entity\TeamAuthorization();
        $teamAuthorization->setTeam($team);
        $teamAuthorization->setRole($role);
        $teamAuthorization->setOrganization($organization);

        $teamService->createAuthorization($teamAuthorization);
    }
}
