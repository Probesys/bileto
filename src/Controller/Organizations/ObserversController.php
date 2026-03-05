<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity;
use App\Repository;
use App\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ObserversController extends BaseController
{
    public function __construct(
        private readonly Repository\OrganizationRepository $organizationRepository,
        private readonly Security\Authorizer $authorizer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        '/organizations/{uid:organization}/observers/{uidUser:user.uid}/switch',
        name: 'switch organization observer',
        methods: ['POST'],
    )]
    public function switch(
        Entity\Organization $organization,
        Entity\User $user,
        Request $request,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:users', $organization);
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $organization);

        $csrfToken = $request->request->getString('_csrf_token');

        if (!$this->isCsrfTokenValid('switch organization observer', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('organization users', [
                'uid' => $organization->getUid(),
            ]);
        }

        if (!$this->authorizer->isGrantedForUser($user, 'orga:see', $organization)) {
            $this->addFlash('error', $this->translator->trans('organization.observers.not_authorized', [], 'errors'));
            return $this->redirectToRoute('organization users', [
                'uid' => $organization->getUid(),
            ]);
        }

        if ($organization->hasObserver($user)) {
            $organization->removeObserver($user);
        } else {
            $organization->addObserver($user);
        }

        $this->organizationRepository->save($organization, true);

        return $this->redirectToRoute('organization users', [
            'uid' => $organization->getUid(),
        ]);
    }
}
