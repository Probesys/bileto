<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Users;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class AnonymizationsController extends BaseController
{
    public function __construct(
        private readonly Service\UserService $userService,
    ) {
    }

    #[Route('/users/{uid:user}/anonymizations/new', name: 'new user anonymization')]
    public function new(Entity\User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');
        $this->denyAccessIfUserIsAnonymized($user);

        /** @var Entity\User */
        $currentUser = $this->getUser();

        if ($user->getUid() === $currentUser->getUid()) {
            throw $this->createNotFoundException('Users cannot anonymize themselves');
        }

        $form = $this->createNamedForm('anonymization', Form\User\AnonymizationForm::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->anonymize($user);

            return $this->redirectToRoute('user', [
                'uid' => $user->getUid(),
            ]);
        }

        return $this->render('users/anonymizations/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
