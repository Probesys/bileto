<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfileController extends BaseController
{
    #[Route('/profile', name: 'profile', methods: ['GET', 'HEAD'])]
    public function edit(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        return $this->render('profile/edit.html.twig', [
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/profile', name: 'update profile', methods: ['POST'])]
    public function update(
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        RequestStack $requestStack
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $initialName = $user->getName();
        $initialEmail = $user->getEmail();

        /** @var string $name */
        $name = $request->request->get('name', $initialName);

        /** @var string $email */
        $email = $request->request->get('email', $initialEmail);

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('update profile', $csrfToken)) {
            return $this->renderBadRequest('profile/edit.html.twig', [
                'name' => $name,
                'email' => $email,
                'error' => $this->csrfError(),
            ]);
        }

        $user->setName($name);
        $user->setEmail($email);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $user->setEmail($initialEmail);
            return $this->renderBadRequest('profile/edit.html.twig', [
                'name' => $name,
                'email' => $email,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $userRepository->save($user, true);

        return $this->redirectToRoute('profile');
    }
}
