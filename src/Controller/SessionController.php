<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SessionController extends BaseController
{
    #[Route('/session/locale', name: 'update session locale', methods: ['POST'])]
    public function updateLocale(
        Request $request,
        RequestStack $requestStack,
        Service\Locales $locales,
    ): Response {
        /** @var string $locale */
        $locale = $request->request->get('locale', $request->getLocale());

        /** @var string $from */
        $from = $request->request->get('from', 'home');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update session locale', $csrfToken)) {
            return $this->redirectToRoute($from);
        }

        if (!$locales->isAvailable($locale)) {
            return $this->redirectToRoute($from);
        }

        $session = $requestStack->getSession();
        $session->set('_locale', $locale);

        return $this->redirectToRoute($from);
    }
}
