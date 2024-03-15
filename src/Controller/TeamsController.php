<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Team;
use App\Form\Type\TeamType;
use App\Repository\TeamRepository;
use App\Service\Sorter\AuthorizationSorter;
use App\Service\Sorter\TeamSorter;
use App\Service\Sorter\UserSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class TeamsController extends BaseController
{
    #[Route('/teams', name: 'teams', methods: ['GET', 'HEAD'])]
    public function index(
        TeamRepository $teamRepository,
        TeamSorter $teamSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $teams = $teamRepository->findAll();
        $teamSorter->sort($teams);

        return $this->render('teams/index.html.twig', [
            'teams' => $teams,
        ]);
    }

    #[Route('/teams/new', name: 'new team', methods: ['GET', 'HEAD'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);

        return $this->render('teams/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/teams/new', name: 'create team', methods: ['POST'])]
    public function create(
        Request $request,
        TeamRepository $teamRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $form = $this->createForm(TeamType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('teams/new.html.twig', [
                'form' => $form,
            ]);
        }

        $team = $form->getData();
        $teamRepository->save($team, true);

        return $this->redirectToRoute('teams');
    }

    #[Route('/teams/{uid}', name: 'team', methods: ['GET', 'HEAD'])]
    public function show(
        Team $team,
        UserSorter $userSorter,
        AuthorizationSorter $authorizationSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $agents = $team->getAgents()->toArray();
        $userSorter->sort($agents);

        $teamAuthorizations = $team->getTeamAuthorizations()->toArray();
        $authorizationSorter->sort($teamAuthorizations);

        return $this->render('teams/show.html.twig', [
            'team' => $team,
            'agents' => $agents,
            'teamAuthorizations' => $teamAuthorizations,
        ]);
    }

    #[Route('/teams/{uid}/edit', name: 'edit team', methods: ['GET', 'HEAD'])]
    public function edit(Team $team): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $form = $this->createForm(TeamType::class, $team);

        return $this->render('teams/edit.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route('/teams/{uid}/edit', name: 'update team', methods: ['POST'])]
    public function update(
        Team $team,
        Request $request,
        TeamRepository $teamRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('teams/edit.html.twig', [
                'team' => $team,
                'form' => $form,
            ]);
        }

        $team = $form->getData();
        $teamRepository->save($team, true);

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }

    #[Route('/teams/{uid}/deletion', name: 'delete team', methods: ['POST'])]
    public function delete(
        Team $team,
        Request $request,
        TeamRepository $teamRepository,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete team', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit team', ['uid' => $team->getUid()]);
        }

        $teamRepository->remove($team, true);

        return $this->redirectToRoute('teams');
    }
}
