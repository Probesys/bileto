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

class TeamsController extends BaseController
{
    public function __construct(
        private readonly Repository\TeamRepository $teamRepository,
        private readonly Service\Sorter\TeamSorter $teamSorter,
        private readonly Service\Sorter\UserSorter $userSorter,
        private readonly Service\Sorter\AuthorizationSorter $authorizationSorter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/teams', name: 'teams', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $teams = $this->teamRepository->findAll();
        $this->teamSorter->sort($teams);

        return $this->render('teams/index.html.twig', [
            'teams' => $teams,
        ]);
    }

    #[Route('/teams/new', name: 'new team')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $team = new Entity\Team();
        $form = $this->createNamedForm('team', Form\TeamForm::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $team = $form->getData();
            $this->teamRepository->save($team, true);

            return $this->redirectToRoute('team', [
                'uid' => $team->getUid(),
            ]);
        }

        return $this->render('teams/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/teams/{uid:team}', name: 'team', methods: ['GET', 'HEAD'])]
    public function show(Entity\Team $team): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $agents = $team->getAgents()->toArray();
        $this->userSorter->sort($agents);

        $teamAuthorizations = $team->getTeamAuthorizations()->toArray();
        $this->authorizationSorter->sort($teamAuthorizations);

        return $this->render('teams/show.html.twig', [
            'team' => $team,
            'agents' => $agents,
            'teamAuthorizations' => $teamAuthorizations,
        ]);
    }

    #[Route('/teams/{uid:team}/edit', name: 'edit team')]
    public function edit(Entity\Team $team, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $form = $this->createNamedForm('team', Form\TeamForm::class, $team);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $team = $form->getData();
            $this->teamRepository->save($team, true);

            return $this->redirectToRoute('team', [
                'uid' => $team->getUid(),
            ]);
        }

        return $this->render('teams/edit.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/teams/{uid:team}/deletion', name: 'delete team', methods: ['POST'])]
    public function delete(Entity\Team $team, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete team', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit team', ['uid' => $team->getUid()]);
        }

        $this->teamRepository->remove($team, true);

        return $this->redirectToRoute('teams');
    }
}
