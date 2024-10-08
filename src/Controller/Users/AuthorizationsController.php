<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Users;

use App\Controller\BaseController;
use App\Entity\Authorization;
use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Repository\RoleRepository;
use App\Security\Authorizer;
use App\Service\Sorter\OrganizationSorter;
use App\Service\Sorter\RoleSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthorizationsController extends BaseController
{
    #[Route('/users/{uid:holder}/authorizations/new', name: 'new user authorization', methods: ['GET', 'HEAD'])]
    public function new(
        User $holder,
        Request $request,
        OrganizationRepository $organizationRepository,
        RoleRepository $roleRepository,
        OrganizationSorter $organizationSorter,
        RoleSorter $roleSorter,
        Authorizer $authorizer,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        /** @var string $defaultOrganizationUid */
        $defaultOrganizationUid = $request->query->get('orga', '');

        $organizations = $organizationRepository->findAll();
        $organizationSorter->sort($organizations);
        $roles = $roleRepository->findBy([
            'type' => ['user', 'agent', 'admin'],
        ]);
        if ($authorizer->isGranted('admin:*')) {
            $superRole = $roleRepository->findOrCreateSuperRole();
            $roles[] = $superRole;
        }
        $roleSorter->sort($roles);

        return $this->render('users/authorizations/new.html.twig', [
            'organizations' => $organizations,
            'roles' => $roles,
            'user' => $holder,
            'type' => 'user',
            'roleUid' => '',
            'organizationUid' => $defaultOrganizationUid,
        ]);
    }

    #[Route('/users/{uid:holder}/authorizations/new', name: 'create user authorization', methods: ['POST'])]
    public function create(
        User $holder,
        Request $request,
        OrganizationRepository $organizationRepository,
        RoleRepository $roleRepository,
        OrganizationSorter $organizationSorter,
        RoleSorter $roleSorter,
        ValidatorInterface $validator,
        Authorizer $authorizer,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $organizations = $organizationRepository->findAll();
        $organizationSorter->sort($organizations);
        $roles = $roleRepository->findAll();
        $roles = $roleRepository->findBy([
            'type' => ['user', 'agent', 'admin'],
        ]);
        if ($authorizer->isGranted('admin:*')) {
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
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
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
                    'role' => $translator->trans('authorization.role.invalid', [], 'errors'),
                ],
            ]);
        }

        if ($role->getType() === 'super' && !$authorizer->isGranted('admin:*')) {
            return $this->renderBadRequest('users/authorizations/new.html.twig', [
                'organizations' => $organizations,
                'roles' => $roles,
                'user' => $holder,
                'type' => $type,
                'roleUid' => $roleUid,
                'organizationUid' => $organizationUid,
                'errors' => [
                    'role' => $translator->trans('authorization.super.unauthorized', [], 'errors'),
                ],
            ]);
        }

        $authorizer->grant($holder, $role, $organization);

        return $this->redirectToRoute('user', [
            'uid' => $holder->getUid(),
        ]);
    }

    #[Route('/authorizations/{uid:authorization}/deletion', name: 'delete user authorization', methods: ['POST'])]
    public function delete(
        Authorization $authorization,
        Request $request,
        Authorizer $authorizer,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $holder = $authorization->getHolder();
        $role = $authorization->getRole();

        if (!$this->isCsrfTokenValid('delete user authorization', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('user', [
                'uid' => $holder->getUid(),
            ]);
        }

        if (
            $role->getType() === 'super' && (
                !$authorizer->isGranted('admin:*') ||
                $user->getId() === $holder->getId()
            )
        ) {
            $this->addFlash('error', $translator->trans('authorization.cannot_revoke.super', [], 'errors'));
            return $this->redirectToRoute('user', [
                'uid' => $holder->getUid(),
            ]);
        }

        if ($authorization->getTeamAuthorization() !== null) {
            $this->addFlash('error', $translator->trans('authorization.cannot_revoke.managed_by_team', [], 'errors'));
            return $this->redirectToRoute('user', [
                'uid' => $holder->getUid(),
            ]);
        }

        $authorizer->ungrant($holder, $authorization);

        return $this->redirectToRoute('user', [
            'uid' => $holder->getUid(),
        ]);
    }
}
