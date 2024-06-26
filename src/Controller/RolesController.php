<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Role;
use App\Repository\RoleRepository;
use App\Service\Sorter\RoleSorter;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Time;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RolesController extends BaseController
{
    #[Route('/roles', name: 'roles', methods: ['GET', 'HEAD'])]
    public function index(RoleRepository $roleRepository, RoleSorter $roleSorter): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        // Make sure the "super" role exists
        $roleRepository->findOrCreateSuperRole();

        $roles = $roleRepository->findAll();
        $roleSorter->sort($roles);

        $rolesByTypes = [
            'super' => null,
            'admin' => [],
            'agent' => [],
            'user' => [],
        ];

        foreach ($roles as $role) {
            if ($role->getType() === 'super') {
                $rolesByTypes['super'] = $role;
            } else {
                $rolesByTypes[$role->getType()][] = $role;
            }
        }

        return $this->render('roles/index.html.twig', [
            'rolesByTypes' => $rolesByTypes,
        ]);
    }

    #[Route('/roles/new', name: 'new role', methods: ['GET', 'HEAD'])]
    public function new(
        Request $request,
        RoleRepository $roleRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        /** @var string $type */
        $type = $request->query->get('type', 'user');

        if (!in_array($type, Role::TYPES) || $type === 'super') {
            $type = 'user';
        }

        $defaultRole = $roleRepository->findDefault();

        return $this->render('roles/new.html.twig', [
            'type' => $type,
            'name' => '',
            'description' => '',
            'permissions' => [],
            'isDefault' => $type === 'user' && !$defaultRole,
            'assignablePermissions' => Role::assignablePermissions($type),
        ]);
    }

    #[Route('/roles/new', name: 'create role', methods: ['POST'])]
    public function create(
        Request $request,
        RoleRepository $roleRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $type */
        $type = $request->request->get('type', 'user');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $description */
        $description = $request->request->get('description', '');

        /** @var string[] $permissions */
        $permissions = $request->request->all('permissions');

        $isDefault = $request->request->getBoolean('isDefault');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!in_array($type, Role::TYPES) || $type === 'super') {
            $type = 'user';
        }

        if ($type === 'admin' && !in_array('admin:see', $permissions)) {
            $permissions[] = 'admin:see';
        } elseif (
            ($type === 'user' || $type === 'agent') &&
            !in_array('orga:see', $permissions)
        ) {
            $permissions[] = 'orga:see';
        }

        if ($type !== 'user') {
            $isDefault = false;
        }

        if (!$this->isCsrfTokenValid('create role', $csrfToken)) {
            return $this->renderBadRequest('roles/new.html.twig', [
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'permissions' => $permissions,
                'isDefault' => $isDefault,
                'assignablePermissions' => Role::assignablePermissions($type),
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $role = new Role();
        $role->setName($name);
        $role->setDescription($description);
        $role->setType($type);
        $role->setPermissions($permissions);
        $role->setIsDefault($isDefault);

        $errors = $validator->validate($role);
        if (count($errors) > 0) {
            return $this->renderBadRequest('roles/new.html.twig', [
                'type' => $type,
                'name' => $name,
                'description' => $description,
                'permissions' => $permissions,
                'isDefault' => $isDefault,
                'assignablePermissions' => Role::assignablePermissions($type),
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $roleRepository->save($role, true);

        if ($role->isDefault() && $role->getType() === 'user') {
            $roleRepository->changeDefault($role);
        }

        return $this->redirectToRoute('roles');
    }

    #[Route('/roles/{uid}/edit', name: 'edit role', methods: ['GET', 'HEAD'])]
    public function edit(Role $role): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        if ($role->getType() === 'super') {
            throw $this->createNotFoundException('Super Role object cannot be loaded.');
        }

        return $this->render('roles/edit.html.twig', [
            'role' => $role,
            'name' => $role->getName(),
            'description' => $role->getDescription(),
            'permissions' => $role->getPermissions(),
            'isDefault' => $role->isDefault(),
            'assignablePermissions' => Role::assignablePermissions($role->getType()),
        ]);
    }

    #[Route('/roles/{uid}/edit', name: 'update role', methods: ['POST'])]
    public function update(
        Role $role,
        Request $request,
        RoleRepository $roleRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        if ($role->getType() === 'super') {
            throw $this->createNotFoundException('Super Role object cannot be loaded.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $description */
        $description = $request->request->get('description', '');

        /** @var string[] $permissions */
        $permissions = $request->request->all('permissions');

        $isDefault = $request->request->getBoolean('isDefault');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $type = $role->getType();
        if ($type === 'admin' && !in_array('admin:see', $permissions)) {
            $permissions[] = 'admin:see';
        } elseif (
            ($type === 'user' || $type === 'agent') &&
            !in_array('orga:see', $permissions)
        ) {
            $permissions[] = 'orga:see';
        }

        if ($type !== 'user') {
            $isDefault = false;
        }

        if (!$this->isCsrfTokenValid('update role', $csrfToken)) {
            return $this->renderBadRequest('roles/edit.html.twig', [
                'role' => $role,
                'name' => $name,
                'description' => $description,
                'permissions' => $permissions,
                'isDefault' => $isDefault,
                'assignablePermissions' => Role::assignablePermissions($type),
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $role->setName($name);
        $role->setDescription($description);
        $role->setPermissions($permissions);
        $role->setIsDefault($isDefault);

        $errors = $validator->validate($role);
        if (count($errors) > 0) {
            return $this->renderBadRequest('roles/edit.html.twig', [
                'role' => $role,
                'name' => $name,
                'description' => $description,
                'permissions' => $permissions,
                'isDefault' => $isDefault,
                'assignablePermissions' => Role::assignablePermissions($type),
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $roleRepository->save($role, true);

        if ($role->isDefault() && $role->getType() === 'user') {
            $roleRepository->changeDefault($role);
        }

        return $this->redirectToRoute('roles');
    }

    #[Route('/roles/{uid}/deletion', name: 'delete role', methods: ['POST'])]
    public function delete(
        Role $role,
        Request $request,
        RoleRepository $roleRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        if ($role->getType() === 'super') {
            throw $this->createNotFoundException('Super Role object cannot be deleted.');
        }

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete role', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit role', ['uid' => $role->getUid()]);
        }

        $roleRepository->remove($role, true);

        return $this->redirectToRoute('roles');
    }
}
