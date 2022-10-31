<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
            'locale' => $user->getLocale(),
        ]);
    }

    #[Route('/preferences', name: 'update preferences', methods: ['POST'])]
    public function update(
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        RequestStack $requestStack
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $colorScheme */
        $colorScheme = $request->request->get('colorScheme', $user->getColorScheme());

        /** @var string $locale */
        $locale = $request->request->get('locale', $user->getLocale());

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update preferences', $csrfToken)) {
            return $this->renderBadRequest('preferences/edit.html.twig', [
                'colorScheme' => $colorScheme,
                'locale' => $locale,
                'error' => $this->csrfError(),
            ]);
        }

        $oldColorScheme = $user->getColorScheme();
        $user->setColorScheme($colorScheme);
        $user->setLocale($locale);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $user->setColorScheme($oldColorScheme);
            return $this->renderBadRequest('preferences/edit.html.twig', [
                'colorScheme' => $colorScheme,
                'locale' => $locale,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $userRepository->save($user, true);

        $session = $requestStack->getSession();
        $session->set('_locale', $user->getLocale());

        return $this->redirectToRoute('preferences');
    }
}
