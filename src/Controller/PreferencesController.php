<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PreferencesController extends BaseController
{
    #[Route('/preferences', name: 'preferences', methods: ['GET', 'HEAD'])]
    public function edit(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        return $this->render('preferences/edit.html.twig', [
            'colorScheme' => $user->getColorScheme(),
        ]);
    }

    #[Route('/preferences', name: 'update preferences', methods: ['POST'])]
    public function update(
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $colorScheme */
        $colorScheme = $request->request->get('colorScheme', 'auto');
        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update preferences', $csrfToken)) {
            return $this->renderBadRequest('preferences/edit.html.twig', [
                'colorScheme' => $colorScheme,
                'error' => $this->csrfError(),
            ]);
        }

        $oldColorScheme = $user->getColorScheme();
        $user->setColorScheme($colorScheme);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $user->setColorScheme($oldColorScheme);
            return $this->renderBadRequest('preferences/edit.html.twig', [
                'colorScheme' => $colorScheme,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $userRepository->save($user, true);

        return $this->redirectToRoute('preferences');
    }
}
