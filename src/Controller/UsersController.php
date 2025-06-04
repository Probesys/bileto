<?php

// This file is part of Bileto.
// Copyright 2022-2025 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use App\Service\Sorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UsersController extends BaseController
{
    #[Route('/users', name: 'users', methods: ['GET', 'HEAD'])]
    public function index(Repository\UserRepository $userRepository, Sorter\UserSorter $userSorter): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $users = $userRepository->findAllWithAuthorizations();
        $userSorter->sort($users);

        return $this->render('users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/new', name: 'new user')]
    public function new(
        Request $request,
        Service\UserCreator $userCreator,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $user = new Entity\User();
        $form = $this->createNamedForm('user', Form\UserForm::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $userCreator->createUser($user);

            return $this->redirectToRoute('new user authorization', [
                'uid' => $user->getUid(),
            ]);
        }

        return $this->render('users/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/{uid:user}', name: 'user', methods: ['GET', 'HEAD'])]
    public function show(
        Entity\User $user,
        Repository\AuthorizationRepository $authorizationRepository,
        Repository\SessionLogRepository $sessionLogRepository,
        Sorter\AuthorizationSorter $authorizationSorter,
        Service\UserService $userService,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $defaultOrganization = $userService->getDefaultOrganization($user);

        $authorizations = $authorizationRepository->findBy([
            'holder' => $user,
        ]);
        $authorizationSorter->sort($authorizations);

        $sessionLogs = $sessionLogRepository->findByIdentifier($user->getUserIdentifier());

        return $this->render('users/show.html.twig', [
            'user' => $user,
            'defaultOrganization' => $defaultOrganization,
            'authorizations' => $authorizations,
            'sessionLogs' => $sessionLogs,
        ]);
    }

    #[Route('/users/{uid:user}/edit', name: 'edit user')]
    public function edit(
        Entity\User $user,
        Request $request,
        Repository\UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');
        $this->denyAccessIfUserIsAnonymized($user);

        $form = $this->createNamedForm('user', Form\UserForm::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $userRepository->save($user, true);

            return $this->redirectToRoute('user', [
                'uid' => $user->getUid(),
            ]);
        }

        return $this->render('users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
