<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Service\RoleSorter;
use App\Utils\Time;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RolesController extends BaseController
{
    #[Route('/roles', name: 'roles', methods: ['GET', 'HEAD'])]
    public function index(RoleRepository $roleRepository, RoleSorter $roleSorter): Response
    {
        $adminRoles = $roleRepository->findBy(['type' => 'admin']);
        $orgaRoles = $roleRepository->findBy(['type' => 'orga']);

        $superRole = $roleRepository->findOrCreateSuperRole();
        array_unshift($adminRoles, $superRole);

        $roleSorter->sort($adminRoles);
        $roleSorter->sort($orgaRoles);

        return $this->render('roles/index.html.twig', [
            'adminRoles' => $adminRoles,
            'orgaRoles' => $orgaRoles,
        ]);
    }

    #[Route('/roles/new', name: 'new role', methods: ['GET', 'HEAD'])]
    public function new(
        Request $request,
        RoleRepository $roleRepository,
    ): Response {
        /** @var string $type */
        $type = $request->query->get('type', 'orga');

        return $this->render('roles/new.html.twig', [
            'type' => $type,
            'name' => '',
            'description' => '',
            'permissions' => [],
        ]);
    }

    #[Route('/roles/new', name: 'create role', methods: ['POST'])]
    public function create(
        Request $request,
        RoleRepository $roleRepository,
        ValidatorInterface $validator
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $type */
        $type = $request->query->get('type', 'orga');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $description */
        $description = $request->request->get('description', '');

        /** @var string[] $permissions */
        $permissions = $request->request->all('permissions');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if ($type !== 'admin' && $type !== 'orga') {
            $type = 'orga';
        }

        if ($type === 'admin' && !in_array('admin:see', $permissions)) {
            $permissions[] = 'admin:see';
        }

        $permissions = Role::sanitizePermissions($type, $permissions);

        if (!$this->isCsrfTokenValid('create role', $csrfToken)) {
            return $this->renderBadRequest('roles/new.html.twig', [
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'permissions' => $permissions,
                'error' => $this->csrfError(),
            ]);
        }

        $role = new Role();
        $uid = $roleRepository->generateUid();
        $role->setUid($uid);
        $role->setCreatedAt(Time::now());
        $role->setCreatedBy($user);

        $role->setName($name);
        $role->setDescription($description);
        $role->setType($type);
        $role->setPermissions($permissions);

        $errors = $validator->validate($role);
        if (count($errors) > 0) {
            return $this->renderBadRequest('roles/new.html.twig', [
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'permissions' => $permissions,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $roleRepository->save($role, true);

        return $this->redirectToRoute('roles');
    }
}
