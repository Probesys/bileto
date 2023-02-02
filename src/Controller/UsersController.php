<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserSorter;
use App\Utils\Random;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var string $email */
        $email = $request->request->get('email', '');

        /** @var string $name */
        $name = $request->request->get('name', '');

        /** @var string $csrfToken */
        $csrfToken = $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('create user', $csrfToken)) {
            return $this->renderBadRequest('users/new.html.twig', [
                'email' => $email,
                'name' => $name,
                'error' => $this->csrfError(),
            ]);
        }

        $newUser = new User();
        $newUser->setEmail($email);
        $newUser->setName($name);
        $uid = $userRepository->generateUid();
        $newUser->setUid($uid);
        $newUser->setLocale($user->getLocale());
        $newUser->setPassword(Random::hex(50));

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
