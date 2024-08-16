<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
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

    #[Route('/users/new', name: 'new user', methods: ['GET', 'HEAD'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $user = new Entity\User();
        $form = $this->createNamedForm('user', Form\UserForm::class, $user);

        return $this->render('users/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/new', name: 'create user', methods: ['POST'])]
    public function create(Request $request, Service\UserCreator $userCreator): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $user = new Entity\User();
        $form = $this->createNamedForm('user', Form\UserForm::class, $user);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('users/new.html.twig', [
                'form' => $form,
            ]);
        }

        $user = $form->getData();
        $userCreator->createUser($user);

        return $this->redirectToRoute('user', [
            'uid' => $user->getUid(),
        ]);
    }

    #[Route('/users/{uid:user}', name: 'user', methods: ['GET', 'HEAD'])]
    public function show(
        Entity\User $user,
        Repository\AuthorizationRepository $authorizationRepository,
        Sorter\AuthorizationSorter $authorizationSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $authorizations = $authorizationRepository->findBy([
            'holder' => $user,
        ]);
        $authorizationSorter->sort($authorizations);

        return $this->render('users/show.html.twig', [
            'user' => $user,
            'authorizations' => $authorizations,
        ]);
    }

    #[Route('/users/{uid:user}/edit', name: 'edit user', methods: ['GET', 'HEAD'])]
    public function edit(Entity\User $user): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $form = $this->createNamedForm('user', Form\UserForm::class, $user);

        return $this->render('users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/users/{uid:user}/edit', name: 'update user', methods: ['POST'])]
    public function update(
        Entity\User $user,
        Request $request,
        Repository\UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $form = $this->createNamedForm('user', Form\UserForm::class, $user);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderBadRequest('users/edit.html.twig', [
                'user' => $user,
                'form' => $form,
            ]);
        }

        $user = $form->getData();
        $userRepository->save($user, true);

        return $this->redirectToRoute('user', [
            'uid' => $user->getUid(),
        ]);
    }
}
