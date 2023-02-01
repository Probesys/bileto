<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
}
