<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Teams;

use App\Controller\BaseController;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActorsLister;
use App\Service\TeamService;
use App\Service\UserCreator;
use App\Service\UserCreatorException;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AgentsController extends BaseController
{
    #[Route('/teams/{uid:team}/agents/new', name: 'new team agent', methods: ['GET', 'HEAD'])]
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

    #[Route('/teams/{uid:team}/agents/new', name: 'add team agent', methods: ['POST'])]
    public function add(
        Team $team,
        Request $request,
        UserRepository $userRepository,
        UserCreator $userCreator,
        TeamService $teamService,
        ActorsLister $actorsLister,
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

        $agent = $userRepository->findOneBy([
            'email' => $agentEmail,
        ]);

        if (!$agent) {
            try {
                $agent = $userCreator->create(
                    email: $agentEmail,
                    locale: $currentUser->getLocale(),
                );
            } catch (UserCreatorException $e) {
                return $this->renderBadRequest('teams/agents/new.html.twig', [
                    'team' => $team,
                    'agents' => $agents,
                    'agentEmail' => $agentEmail,
                    'errors' => ConstraintErrorsFormatter::format($e->getErrors()),
                ]);
            }
        }

        $teamService->addAgent($team, $agent);

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }

    #[Route('/teams/{uid:team}/agents/deletion', name: 'remove team agent', methods: ['POST'])]
    public function remove(
        Team $team,
        Request $request,
        UserRepository $userRepository,
        TeamService $teamService,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var string */
        $agentUid = $request->request->get('agentUid', '');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('remove team agent', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));

            return $this->redirectToRoute('team', [
                'uid' => $team->getUid(),
            ]);
        }

        $agent = $userRepository->findOneBy(['uid' => $agentUid]);
        if ($agent) {
            $teamService->removeAgent($team, $agent);
        }

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }
}
