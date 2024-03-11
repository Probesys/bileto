<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Teams;

use App\Controller\BaseController;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Service\ActorsLister;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AgentsController extends BaseController
{
    #[Route('/teams/{uid}/agents/new', name: 'new team agent', methods: ['GET', 'HEAD'])]
    public function new(
        Team $team,
        ActorsLister $actorsLister,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $agents = $actorsLister->findAll('agent');
        $agents = array_filter($agents, function (User $agent) use ($team): bool {
            return !$team->hasAgent($agent);
        });

        return $this->render('teams/agents/new.html.twig', [
            'team' => $team,
            'agents' => $agents,
            'agentEmail' => '',
        ]);
    }

    #[Route('/teams/{uid}/agents/new', name: 'add team agent', methods: ['POST'])]
    public function add(
        Team $team,
        Request $request,
        UserRepository $userRepository,
        TeamRepository $teamRepository,
        ActorsLister $actorsLister,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var \App\Entity\User */
        $currentUser = $this->getUser();

        /** @var string */
        $agentEmail = $request->request->get('agentEmail', '');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        $agents = $actorsLister->findAll('agent');
        $agents = array_filter($agents, function (User $agent) use ($team): bool {
            return !$team->hasAgent($agent);
        });

        if (!$this->isCsrfTokenValid('add team agent', $csrfToken)) {
            return $this->renderBadRequest('teams/agents/new.html.twig', [
                'team' => $team,
                'agents' => $agents,
                'agentEmail' => $agentEmail,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $agent = $userRepository->findOneOrBuildBy([
            'email' => $agentEmail,
        ], [
            'locale' => $currentUser->getLocale(),
        ]);

        $errors = $validator->validate($agent);
        if (count($errors) > 0) {
            return $this->renderBadRequest('teams/agents/new.html.twig', [
                'team' => $team,
                'agents' => $agents,
                'agentEmail' => $agentEmail,
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $userRepository->save($agent, true);

        $team->addAgent($agent);
        $teamRepository->save($team, true);

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }
}
