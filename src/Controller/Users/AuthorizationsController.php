<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Users;

use App\Controller\BaseController;
use App\Entity\Authorization;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use App\Repository\OrganizationRepository;
use App\Repository\RoleRepository;
use App\Service\AuthorizationSorter;
use App\Service\OrganizationSorter;
use App\Service\RoleSorter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorizationsController extends BaseController
{
    #[Route('/users/{uid}/authorizations', name: 'user authorizations', methods: ['GET', 'HEAD'])]
    public function index(
        User $holder,
        AuthorizationRepository $authorizationRepository,
        AuthorizationSorter $authorizationSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $authorizations = $authorizationRepository->findBy([
            'holder' => $holder,
        ]);
        $authorizationSorter->sort($authorizations);

        return $this->render('users/authorizations/index.html.twig', [
            'user' => $holder,
            'authorizations' => $authorizations,
        ]);
    }

    #[Route('/users/{uid}/authorizations/new', name: 'new user authorization', methods: ['GET', 'HEAD'])]
    public function new(
        User $holder,
        OrganizationRepository $organizationRepository,
        RoleRepository $roleRepository,
        OrganizationSorter $organizationSorter,
        RoleSorter $roleSorter,
        Security $security,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $organizations = $organizationRepository->findAll();
        $organizations = $organizationSorter->asTree($organizations);
        $roles = $roleRepository->findBy([
            'type' => ['orga', 'admin'],
        ]);
        if ($security->isGranted('admin:*')) {
            $superRole = $roleRepository->findOrCreateSuperRole();
            $roles[] = $superRole;
        }
        $roleSorter->sort($roles);

        return $this->render('users/authorizations/new.html.twig', [
            'organizations' => $organizations,
            'roles' => $roles,
            'user' => $holder,
            'type' => 'orga',
            'roleUid' => '',
            'organizationUid' => '',
        ]);
    }

    #[Route('/users/{uid}/authorizations/new', name: 'create user authorization', methods: ['POST'])]
    public function create(
        User $holder,
        Request $request,
        AuthorizationRepository $authorizationRepository,
        OrganizationRepository $organizationRepository,
        RoleRepository $roleRepository,
        OrganizationSorter $organizationSorter,
        RoleSorter $roleSorter,
        ValidatorInterface $validator,
        Security $security,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $organizations = $organizationRepository->findAll();
        $organizations = $organizationSorter->asTree($organizations);
        $roles = $roleRepository->findAll();
        $roles = $roleRepository->findBy([
            'type' => ['orga', 'admin'],
        ]);
        if ($security->isGranted('admin:*')) {
            $superRole = $roleRepository->findOrCreateSuperRole();
            $roles[] = $superRole;
        }
        $roleSorter->sort($roles);

        /** @var string $type */
        $type = $request->request->get('type', 'orga');

        /** @var string $roleUid */
        $roleUid = $request->request->get('role', '');

        /** @var string $organizationUid */
        $organizationUid = $request->request->get('organization', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create user authorization', $csrfToken)) {
            return $this->renderBadRequest('users/authorizations/new.html.twig', [
                'organizations' => $organizations,
                'roles' => $roles,
                'user' => $holder,
                'type' => $type,
                'roleUid' => $roleUid,
                'organizationUid' => $organizationUid,
                'error' => $this->csrfError(),
            ]);
        }

        $role = $roleRepository->findOneBy(['uid' => $roleUid]);
        $organization = $organizationRepository->findOneBy(['uid' => $organizationUid]);

        if (!$role) {
            return $this->renderBadRequest('users/authorizations/new.html.twig', [
                'organizations' => $organizations,
                'roles' => $roles,
                'user' => $holder,
                'type' => $type,
                'roleUid' => $roleUid,
                'organizationUid' => $organizationUid,
                'errors' => [
                    'role' => new TranslatableMessage('Choose a role from the list.', [], 'errors'),
                ],
            ]);
        }

        if ($role->getType() === 'super' && !$security->isGranted('admin:*')) {
            return $this->renderBadRequest('users/authorizations/new.html.twig', [
                'organizations' => $organizations,
                'roles' => $roles,
                'user' => $holder,
                'type' => $type,
                'roleUid' => $roleUid,
                'organizationUid' => $organizationUid,
                'errors' => [
                    'role' => new TranslatableMessage('You can’t grant super-admin authorization.', [], 'errors'),
                ],
            ]);
        }

        if ($role->getType() === 'admin' || $role->getType() === 'super') {
            $existingRole = $authorizationRepository->getAdminAuthorizationFor($holder);
            if ($existingRole) {
                return $this->renderBadRequest('users/authorizations/new.html.twig', [
                    'organizations' => $organizations,
                    'roles' => $roles,
                    'user' => $holder,
                    'type' => $type,
                    'roleUid' => $roleUid,
                    'organizationUid' => $organizationUid,
                    'error' => new TranslatableMessage('This user already has an admin role.', [], 'errors'),
                ]);
            }
        } else {
            $existingRole = $authorizationRepository->getOrgaAuthorizationFor($holder, $organization);
            if ($existingRole && $existingRole->getOrganization() === $organization) {
                return $this->renderBadRequest('users/authorizations/new.html.twig', [
                    'organizations' => $organizations,
                    'roles' => $roles,
                    'user' => $holder,
                    'type' => $type,
                    'roleUid' => $roleUid,
                    'organizationUid' => $organizationUid,
                    'error' => new TranslatableMessage(
                        'This user already has an orga role for this organization.',
                        [],
                        'errors',
                    ),
                ]);
            }
        }

        $authorizationRepository->grant($holder, $role, $organization);

        return $this->redirectToRoute('user authorizations', [
            'uid' => $holder->getUid(),
        ]);
    }

    #[Route('/authorizations/{uid}/deletion', name: 'delete user authorization', methods: ['POST'])]
    public function delete(
        Authorization $authorization,
        Request $request,
        AuthorizationRepository $authorizationRepository,
        Security $security,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $holder = $authorization->getHolder();
        $role = $authorization->getRole();

        if (!$this->isCsrfTokenValid('delete user authorization', $csrfToken)) {
            $this->addFlash('error', $this->csrfError());
            return $this->redirectToRoute('user authorizations', [
                'uid' => $holder->getUid(),
            ]);
        }

        if (
            $role->getType() === 'super' && (
                !$security->isGranted('admin:*') ||
                $user->getId() === $holder->getId()
            )
        ) {
            $this->addFlash('error', new TranslatableMessage(
                'You can’t revoke this authorization.',
                [],
                'errors'
            ));
            return $this->redirectToRoute('user authorizations', [
                'uid' => $holder->getUid(),
            ]);
        }

        $authorizationRepository->remove($authorization, true);

        return $this->redirectToRoute('user authorizations', [
            'uid' => $holder->getUid(),
        ]);
    }
}
