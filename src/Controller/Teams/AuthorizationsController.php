<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Teams;

use App\Controller\BaseController;
use App\Entity\Team;
use App\Entity\TeamAuthorization;
use App\Repository\AuthorizationRepository;
use App\Repository\OrganizationRepository;
use App\Repository\RoleRepository;
use App\Repository\TeamAuthorizationRepository;
use App\Service\Sorter\OrganizationSorter;
use App\Service\Sorter\RoleSorter;
use App\Service\TeamService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthorizationsController extends BaseController
{
    #[Route('/teams/{uid}/authorizations/new', name: 'new team authorization', methods: ['GET', 'HEAD'])]
    public function new(
        Team $team,
        OrganizationRepository $organizationRepository,
        RoleRepository $roleRepository,
        OrganizationSorter $organizationSorter,
        RoleSorter $roleSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $organizations = $organizationRepository->findAll();
        $organizationSorter->sort($organizations);
        $roles = $roleRepository->findBy(['type' => 'agent']);
        $roleSorter->sort($roles);

        return $this->render('teams/authorizations/new.html.twig', [
            'team' => $team,
            'organizations' => $organizations,
            'roles' => $roles,
            'roleUid' => '',
            'organizationUid' => '',
        ]);
    }

    #[Route('/teams/{uid}/authorizations/new', name: 'create team authorization', methods: ['POST'])]
    public function create(
        Team $team,
        Request $request,
        OrganizationRepository $organizationRepository,
        RoleRepository $roleRepository,
        TeamService $teamService,
        OrganizationSorter $organizationSorter,
        RoleSorter $roleSorter,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var string */
        $roleUid = $request->request->get('role', '');

        /** @var string */
        $organizationUid = $request->request->get('organization', '');

        $organizations = $organizationRepository->findAll();
        $organizationSorter->sort($organizations);
        $roles = $roleRepository->findBy(['type' => 'agent']);
        $roleSorter->sort($roles);

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create team authorization', $csrfToken)) {
            return $this->renderBadRequest('teams/authorizations/new.html.twig', [
                'team' => $team,
                'organizations' => $organizations,
                'roles' => $roles,
                'roleUid' => $roleUid,
                'organizationUid' => $organizationUid,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $role = $roleRepository->findOneBy(['uid' => $roleUid]);
        $organization = $organizationRepository->findOneBy(['uid' => $organizationUid]);

        if (!$role || $role->getType() !== 'agent') {
            return $this->renderBadRequest('teams/authorizations/new.html.twig', [
                'team' => $team,
                'organizations' => $organizations,
                'roles' => $roles,
                'roleUid' => $roleUid,
                'organizationUid' => $organizationUid,
                'errors' => [
                    'role' => $translator->trans('authorization.role.invalid', [], 'errors'),
                ],
            ]);
        }

        $teamService->createAuthorization($team, $role, $organization);

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }

    #[Route('/team-authorizations/{uid}/deletion', name: 'delete team authorization', methods: ['POST'])]
    public function delete(
        TeamAuthorization $teamAuthorization,
        Request $request,
        TeamService $teamService,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        $team = $teamAuthorization->getTeam();

        if (!$this->isCsrfTokenValid('delete team authorization', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('team', [
                'uid' => $team->getUid(),
            ]);
        }

        $teamService->removeAuthorization($teamAuthorization);

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }
}
