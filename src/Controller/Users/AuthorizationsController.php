<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Users;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use App\Security;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthorizationsController extends BaseController
{
    #[Route('/users/{uid:holder}/authorizations/new', name: 'new user authorization')]
    public function new(
        Entity\User $holder,
        Request $request,
        Repository\AuthorizationRepository $authorizationRepository,
        Repository\OrganizationRepository $organizationRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');
        $this->denyAccessIfUserIsAnonymized($holder);

        $defaultOrganizationUid = $request->query->getString('orga', '');

        $defaultOrganization = $organizationRepository->findOneBy([
            'uid' => $defaultOrganizationUid,
        ]);

        $authorization = new Entity\Authorization();
        $authorization->setHolder($holder);
        $authorization->setOrganization($defaultOrganization);

        $form = $this->createNamedForm('authorization', Form\User\AuthorizationForm::class, $authorization);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $authorization = $form->getData();
            $authorizationRepository->save($authorization, true);

            return $this->redirectToRoute('user', [
                'uid' => $holder->getUid(),
            ]);
        }

        return $this->render('users/authorizations/new.html.twig', [
            'user' => $holder,
            'form' => $form,
        ]);
    }

    #[Route('/authorizations/{uid:authorization}/deletion', name: 'delete user authorization', methods: ['POST'])]
    public function delete(
        Entity\Authorization $authorization,
        Request $request,
        Security\Authorizer $authorizer,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        /** @var Entity\User */
        $user = $this->getUser();

        $csrfToken = $request->request->getString('_csrf_token', '');

        $holder = $authorization->getHolder();
        $role = $authorization->getRole();

        if (!$this->isCsrfTokenValid('delete user authorization', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('user', [
                'uid' => $holder->getUid(),
            ]);
        }

        if (
            $role->getType() === 'super' && (
                !$authorizer->isGranted('admin:*') ||
                $user->getId() === $holder->getId()
            )
        ) {
            $this->addFlash('error', $translator->trans('authorization.cannot_revoke.super', [], 'errors'));
            return $this->redirectToRoute('user', [
                'uid' => $holder->getUid(),
            ]);
        }

        if ($authorization->getTeamAuthorization() !== null) {
            $this->addFlash('error', $translator->trans('authorization.cannot_revoke.managed_by_team', [], 'errors'));
            return $this->redirectToRoute('user', [
                'uid' => $holder->getUid(),
            ]);
        }

        $authorizer->ungrant($holder, $authorization);

        return $this->redirectToRoute('user', [
            'uid' => $holder->getUid(),
        ]);
    }
}
