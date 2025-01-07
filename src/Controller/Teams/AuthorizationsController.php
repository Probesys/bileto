<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Teams;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthorizationsController extends BaseController
{
    #[Route('/teams/{uid:team}/authorizations/new', name: 'new team authorization')]
    public function new(
        Entity\Team $team,
        Request $request,
        Service\TeamService $teamService,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:agents');

        $teamAuthorization = new Entity\TeamAuthorization();
        $teamAuthorization->setTeam($team);

        $form = $this->createNamedForm('authorization', Form\Team\AuthorizationForm::class, $teamAuthorization);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $teamAuthorization = $form->getData();
            $teamService->createAuthorization($teamAuthorization);

            return $this->redirectToRoute('team', [
                'uid' => $team->getUid(),
            ]);
        }

        return $this->render('teams/authorizations/new.html.twig', [
            'team' => $team,
            'form' => $form,
        ]);
    }

    #[Route(
        '/team-authorizations/{uid:teamAuthorization}/deletion',
        name: 'delete team authorization',
        methods: ['POST']
    )]
    public function delete(
        Entity\TeamAuthorization $teamAuthorization,
        Request $request,
        Service\TeamService $teamService,
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
