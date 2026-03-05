<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RolesController extends BaseController
{
    public function __construct(
        private readonly Repository\RoleRepository $roleRepository,
        private readonly Service\Sorter\RoleSorter $roleSorter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/roles', name: 'roles', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:roles');
        // Make sure the "super" role exists
        $this->roleRepository->findOrCreateSuperRole();

        $roles = $this->roleRepository->findAll();
        $this->roleSorter->sort($roles);

        $rolesByTypes = [
            'super' => null,
            'admin' => [],
            'agent' => [],
            'user' => [],
        ];

        foreach ($roles as $role) {
            /** @var value-of<Entity\Role::TYPES> */
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
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        /** @var string */
        $type = $request->query->get('type', 'user');

        if (!in_array($type, Entity\Role::TYPES) || $type === 'super') {
            $type = 'user';
        }

        $role = new Entity\Role();

        $defaultRole = $this->roleRepository->findDefault();
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
                $this->roleRepository->unsetDefault();
            }

            $this->roleRepository->save($role, true);

            return $this->redirectToRoute('roles');
        }

        return $this->render('roles/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/roles/{uid:role}/edit', name: 'edit role')]
    public function edit(Entity\Role $role, Request $request): Response
    {
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
                $this->roleRepository->unsetDefault();
            }

            $this->roleRepository->save($role, true);

            return $this->redirectToRoute('roles');
        }

        return $this->render('roles/edit.html.twig', [
            'role' => $role,
            'form' => $form,
        ]);
    }

    #[Route('/roles/{uid:role}/deletion', name: 'delete role', methods: ['POST'])]
    public function delete(Entity\Role $role, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        if ($role->getType() === 'super') {
            throw $this->createNotFoundException('Super Role object cannot be deleted.');
        }

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete role', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit role', ['uid' => $role->getUid()]);
        }

        $this->roleRepository->remove($role, true);

        return $this->redirectToRoute('roles');
    }
}
