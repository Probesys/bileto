<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Role;
use App\Form;
use App\Repository\RoleRepository;
use App\Service\Sorter\RoleSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
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
            /** @var value-of<Role::TYPES> */
            $type = $role->getType();

            if ($type === 'super') {
                $rolesByTypes['super'] = $role;
            } else {
                $rolesByTypes[$type][] = $role;
            }
        }

        return $this->render('roles/index.html.twig', [
            'rolesByTypes' => $rolesByTypes,
        ]);
    }

    #[Route('/roles/new', name: 'new role')]
    public function new(
        Request $request,
        RoleRepository $roleRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        /** @var string */
        $type = $request->query->get('type', 'user');

        if (!in_array($type, Role::TYPES) || $type === 'super') {
            $type = 'user';
        }

        $role = new Role();

        $defaultRole = $roleRepository->findDefault();
        if ($type === 'user' && !$defaultRole) {
            $role->setIsDefault(true);
        }

        $form = $this->createNamedForm('role', Form\RoleForm::class, $role, [
            'type' => $type,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->getData();

            if ($role->isDefault() && $role->getType() === 'user') {
                $roleRepository->unsetDefault();
            }

            $roleRepository->save($role, true);

            return $this->redirectToRoute('roles');
        }

        return $this->render('roles/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/roles/{uid:role}/edit', name: 'edit role')]
    public function edit(
        Role $role,
        Request $request,
        RoleRepository $roleRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        if ($role->getType() === 'super') {
            throw $this->createNotFoundException('Super Role object cannot be loaded.');
        }

        $form = $this->createNamedForm('role', Form\RoleForm::class, $role, [
            'type' => $role->getType(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->getData();

            if ($role->isDefault() && $role->getType() === 'user') {
                $roleRepository->unsetDefault();
            }

            $roleRepository->save($role, true);

            return $this->redirectToRoute('roles');
        }

        return $this->render('roles/edit.html.twig', [
            'role' => $role,
            'form' => $form,
        ]);
    }

    #[Route('/roles/{uid:role}/deletion', name: 'delete role', methods: ['POST'])]
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
