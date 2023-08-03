<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Repository\UserRepository;
use App\Utils\ConstraintErrorsFormatter;
use App\Utils\Locales;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Router;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
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
            'availableLanguages' => Locales::getSupportedLanguages(),
        ]);
    }

    #[Route('/preferences', name: 'update preferences', methods: ['POST'])]
    public function update(
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        RequestStack $requestStack,
        TranslatorInterface $translator,
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
                'availableLanguages' => Locales::getSupportedLanguages(),
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
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
                'availableLanguages' => Locales::getSupportedLanguages(),
                'errors' => ConstraintErrorsFormatter::format($errors),
            ]);
        }

        $userRepository->save($user, true);

        $session = $requestStack->getSession();
        $session->set('_locale', $user->getLocale());

        $this->addFlash('success', new TranslatableMessage('notifications.saved'));

        return $this->redirectToRoute('preferences');
    }

    #[Route('/preferences/hide-events', name: 'update hide events', methods: ['POST'])]
    public function updateHideEvents(
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        RequestStack $requestStack,
        TranslatorInterface $translator,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $hideEvents = $request->request->getBoolean('hideEvents', false);

        /** @var string $from */
        $from = $request->request->get('from', '/preferences');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isPathRedirectable($from)) {
            throw $this->createNotFoundException('From parameter does not match any valid route.');
        }

        if (!$this->isCsrfTokenValid('update hide events', $csrfToken)) {
            $this->addFlash('error', $translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirect($from);
        }

        $user->setHideEvents($hideEvents);
        $userRepository->save($user, true);

        return $this->redirect($from);
    }
}
