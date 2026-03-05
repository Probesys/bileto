<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Teams;

use App\Controller\BaseController;
use App\Entity;
use App\Repository;
use App\Service;
use App\Utils\ConstraintErrorsFormatter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AgentsController extends BaseController
{
    public function __construct(
        private readonly Service\ActorsLister $actorsLister,
        private readonly Repository\UserRepository $userRepository,
        private readonly Service\UserCreator $userCreator,
        private readonly Service\TeamService $teamService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/teams/{uid:team}/agents/new', name: 'new team agent', methods: ['GET', 'HEAD'])]
    public function new(Entity\Team $team): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $agents = $this->actorsLister->findAll('agent');
        $agents = array_filter($agents, function (Entity\User $agent) use ($team): bool {
            return !$team->hasAgent($agent);
        });

        return $this->render('teams/agents/new.html.twig', [
            'team' => $team,
            'agents' => $agents,
            'agentEmail' => '',
        ]);
    }

    #[Route('/teams/{uid:team}/agents/new', name: 'add team agent', methods: ['POST'])]
    public function add(Entity\Team $team, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var Entity\User */
        $currentUser = $this->getUser();

        /** @var string */
        $agentEmail = $request->request->get('agentEmail', '');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        $agents = $this->actorsLister->findAll('agent');
        $agents = array_filter($agents, function (Entity\User $agent) use ($team): bool {
            return !$team->hasAgent($agent);
        });

        if (!$this->isCsrfTokenValid('add team agent', $csrfToken)) {
            return $this->renderBadRequest('teams/agents/new.html.twig', [
                'team' => $team,
                'agents' => $agents,
                'agentEmail' => $agentEmail,
                'error' => $this->translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $agent = $this->userRepository->findOneBy([
            'email' => $agentEmail,
        ]);

        if (!$agent) {
            try {
                $agent = $this->userCreator->create(
                    email: $agentEmail,
                    locale: $currentUser->getLocale(),
                );
            } catch (Service\UserCreatorException $e) {
                return $this->renderBadRequest('teams/agents/new.html.twig', [
                    'team' => $team,
                    'agents' => $agents,
                    'agentEmail' => $agentEmail,
                    'errors' => ConstraintErrorsFormatter::format($e->getErrors()),
                ]);
            }
        }

        $this->teamService->addAgent($team, $agent);

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }

    #[Route('/teams/{uid:team}/agents/deletion', name: 'remove team agent', methods: ['POST'])]
    public function remove(Entity\Team $team, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        /** @var string */
        $agentUid = $request->request->get('agentUid', '');

        /** @var string */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('remove team agent', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));

            return $this->redirectToRoute('team', [
                'uid' => $team->getUid(),
            ]);
        }

        $agent = $this->userRepository->findOneBy(['uid' => $agentUid]);
        if ($agent) {
            $this->teamService->removeAgent($team, $agent);
        }

        return $this->redirectToRoute('team', [
            'uid' => $team->getUid(),
        ]);
    }
}
