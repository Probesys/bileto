<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Entity;
use App\Form;
use App\Repository;
use App\Service;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersController extends BaseController
{
    public function __construct(
        private readonly Repository\UserRepository $userRepository,
        private readonly Service\Sorter\UserSorter $userSorter,
        private readonly Service\UserCreator $userCreator,
        private readonly Repository\AuthorizationRepository $authorizationRepository,
        private readonly Repository\SessionLogRepository $sessionLogRepository,
        private readonly Service\Sorter\AuthorizationSorter $authorizationSorter,
        private readonly Service\UserService $userService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/users', name: 'users', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $users = $this->userRepository->findAllWithAuthorizations();
        $this->userSorter->sort($users);

        return $this->render('users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/new', name: 'new user')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $user = new Entity\User();
        $form = $this->createNamedForm('user', Form\UserForm::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $this->userCreator->createUser($user);

            return $this->redirectToRoute('new user authorization', [
                'uid' => $user->getUid(),
            ]);
        }

        return $this->render('users/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/{uid:user}', name: 'user', methods: ['GET', 'HEAD'])]
    public function show(Entity\User $user): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $defaultOrganization = $this->userService->getDefaultOrganization($user);

        $authorizations = $this->authorizationRepository->findBy([
            'holder' => $user,
        ]);
        $this->authorizationSorter->sort($authorizations);

        $sessionLogs = $this->sessionLogRepository->findByIdentifier($user->getUserIdentifier());

        return $this->render('users/show.html.twig', [
            'user' => $user,
            'defaultOrganization' => $defaultOrganization,
            'authorizations' => $authorizations,
            'sessionLogs' => $sessionLogs,
        ]);
    }

    #[Route('/users/{uid:user}/edit', name: 'edit user')]
    public function edit(Entity\User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');
        $this->denyAccessIfUserIsAnonymized($user);

        $form = $this->createNamedForm('user', Form\UserForm::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $this->userRepository->save($user, true);

            return $this->redirectToRoute('user', [
                'uid' => $user->getUid(),
            ]);
        }

        return $this->render('users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/users/{uid:user}/deletion', name: 'delete user', methods: ['POST'])]
    public function delete(Entity\User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted('admin:manage:users');

        /** @var Entity\User */
        $currentUser = $this->getUser();

        if ($user->getUid() === $currentUser->getUid()) {
            throw $this->createAccessDeniedException('Users cannot delete themselves');
        }

        $csrfToken = $request->request->getString('_csrf_token', '');

        if (!$this->isCsrfTokenValid('delete user', $csrfToken)) {
            $this->addFlash('error', $this->translator->trans('csrf.invalid', [], 'errors'));
            return $this->redirectToRoute('edit user', ['uid' => $user->getUid()]);
        }

        $this->userRepository->remove($user, true);

        return $this->redirectToRoute('users');
    }
}
