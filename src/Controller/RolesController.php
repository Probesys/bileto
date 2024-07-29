<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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

        $form = $this->createForm(Form\Type\RoleType::class, $role, [
            'type' => $type,
        ]);

        return $this->render('roles/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/roles/new', name: 'create role', methods: ['POST'])]
    public function create(
        Request $request,
        RoleRepository $roleRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        $data = $request->request->all('role');
        $type = $data['type'] ?? 'user';

        if (!in_array($type, Role::TYPES) || $type === 'super') {
            $type = 'user';
        }

        $form = $this->createForm(Form\Type\RoleType::class, options: [
            'type' => $type,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('roles/new.html.twig', [
                'form' => $form,
            ]);
        }

        $role = $form->getData();
        $roleRepository->save($role, true);

        if ($role->isDefault() && $role->getType() === 'user') {
            $roleRepository->changeDefault($role);
        }

        return $this->redirectToRoute('roles');
    }

    #[Route('/roles/{uid:role}/edit', name: 'edit role', methods: ['GET', 'HEAD'])]
    public function edit(Role $role): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        if ($role->getType() === 'super') {
            throw $this->createNotFoundException('Super Role object cannot be loaded.');
        }

        $form = $this->createForm(Form\Type\RoleType::class, $role, [
            'type' => $role->getType(),
        ]);

        return $this->render('roles/edit.html.twig', [
            'role' => $role,
            'form' => $form,
        ]);
    }

    #[Route('/roles/{uid:role}/edit', name: 'update role', methods: ['POST'])]
    public function update(
        Role $role,
        Request $request,
        RoleRepository $roleRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:roles');

        if ($role->getType() === 'super') {
            throw $this->createNotFoundException('Super Role object cannot be loaded.');
        }

        $form = $this->createForm(Form\Type\RoleType::class, $role, options: [
            'type' => $role->getType(),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('roles/edit.html.twig', [
                'role' => $role,
                'form' => $form,
            ]);
        }

        $role = $form->getData();
        $roleRepository->save($role, true);

        if ($role->isDefault() && $role->getType() === 'user') {
            $roleRepository->changeDefault($role);
        }

        return $this->redirectToRoute('roles');
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
