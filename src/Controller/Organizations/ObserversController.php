<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity;
use App\Repository;
use App\Security;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ObserversController extends BaseController
{
    #[Route(
        '/organizations/{uid:organization}/observers/{uidUser:user}/switch',
        name: 'switch organization observer',
        methods: ['POST'],
    )]
    public function switch(
        Entity\Organization $organization,
        #[MapEntity(mapping: ['user' => 'uid'])]
        Entity\User $user,
        Request $request,
        Repository\OrganizationRepository $organizationRepository,
        Security\Authorizer $authorizer,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:users', $organization);
        $this->denyAccessUnlessGranted('orga:update:tickets:actors', $organization);

        $csrfToken = $request->request->getString('_csrf_token');

        if (!$this->isCsrfTokenValid('switch organization observer', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('organization users', [
                'uid' => $organization->getUid(),
            ]);
        }

        if (!$authorizer->isGrantedToUser($user, 'orga:see', $organization)) {
            $this->addFlash('error', $translator->trans('organization.observers.not_authorized', [], 'errors'));
            return $this->redirectToRoute('organization users', [
                'uid' => $organization->getUid(),
            ]);
        }

        if ($organization->hasObserver($user)) {
            $organization->removeObserver($user);
        } else {
            $organization->addObserver($user);
        }

        $organizationRepository->save($organization, true);

        return $this->redirectToRoute('organization users', [
            'uid' => $organization->getUid(),
        ]);
    }
}
