<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Users;

use App\Controller\BaseController;
use App\Entity;
use App\Form;
use App\Repository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class PreferencesController extends BaseController
{
    public function __construct(
        private readonly Repository\UserRepository $userRepository,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/preferences', name: 'preferences')]
    public function edit(Request $request): Response
    {
        /** @var Entity\User */
        $user = $this->getUser();

        $form = $this->createNamedForm('preferences', Form\User\PreferencesForm::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $this->userRepository->save($user, true);

            $session = $this->requestStack->getSession();
            $session->set('_locale', $user->getLocale());

            $this->addFlash('success', new TranslatableMessage('notifications.saved'));

            return $this->redirectToRoute('preferences');
        }

        return $this->render('users/preferences/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/preferences/hide-events', name: 'update hide events', methods: ['POST'])]
    public function updateHideEvents(Request $request): Response
    {
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
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirect($from);
        }

        $user->setHideEvents($hideEvents);
        $this->userRepository->save($user, true);

        return $this->redirect($from);
    }
}
