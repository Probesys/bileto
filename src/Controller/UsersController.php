<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserSorter;
use App\Utils\Time;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersController extends BaseController
{
    #[Route('/users', name: 'users', methods: ['GET', 'HEAD'])]
    public function index(UserRepository $userRepository, UserSorter $userSorter): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $users = $userRepository->findAll();
        $userSorter->sort($users);

        return $this->render('users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/new', name: 'new user', methods: ['GET', 'HEAD'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        return $this->render('users/new.html.twig', [
            'email' => '',
            'name' => '',
        ]);
    }

    #[Route('/users/new', name: 'create user', methods: ['POST'])]
    public function create(
        Request $request,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $email */
        $email = $request->request->get('email', '');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $password */
        $password = $request->request->get('password', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create user', $csrfToken)) {
            return $this->renderBadRequest('users/new.html.twig', [
                'email' => $email,
                'name' => $name,
                'error' => $translator->trans('csrf.invalid', [], 'errors'),
            ]);
        }

        $newUser = new User();
        $newUser->setEmail($email);
        $newUser->setName($name);
        $newUser->setLocale($user->getLocale());
        $newUser->setPassword('');

        if ($password !== '') {
            $hashedPassword = $passwordHasher->hashPassword($newUser, $password);
            $newUser->setPassword($hashedPassword);
        }

        $errors = $validator->validate($newUser);
        if (count($errors) > 0) {
            return $this->renderBadRequest('users/new.html.twig', [
                'email' => $email,
                'name' => $name,
                'errors' => $this->formatErrors($errors),
            ]);
        }

        $userRepository->save($newUser, true);

        return $this->redirectToRoute('users');
    }
}
