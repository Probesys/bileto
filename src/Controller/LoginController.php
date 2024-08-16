<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends BaseController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function new(AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        if ($user) {
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        // last identifier entered by the user
        $lastIdentifier = $authenticationUtils->getLastUsername();

        return $this->render('login/new.html.twig', [
            'last_identifier' => $lastIdentifier,
            'availableLanguages' => Service\Locales::SUPPORTED_LOCALES,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        // controller can be blank: it will never be called!
        throw new \Exception('Don’t forget to activate logout in security.yaml'); // @codeCoverageIgnore
    }
}
