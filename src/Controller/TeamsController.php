<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\Team;
use App\Form\Type\TeamType;
use App\Repository\TeamRepository;
use App\Service\Sorter\TeamSorter;
use App\Service\Sorter\UserSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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
    public function show(Team $team, UserSorter $userSorter): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $agents = $team->getAgents()->toArray();
        $userSorter->sort($agents);

        return $this->render('teams/show.html.twig', [
            'team' => $team,
            'agents' => $agents,
        ]);
    }
}
