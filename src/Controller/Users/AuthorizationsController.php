<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Users;

use App\Controller\BaseController;
use App\Entity\User;
use App\Repository\AuthorizationRepository;
use App\Service\AuthorizationSorter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthorizationsController extends BaseController
{
    #[Route('/users/{uid}/authorizations', name: 'user authorizations', methods: ['GET', 'HEAD'])]
    public function index(
        User $user,
        AuthorizationRepository $authorizationRepository,
        AuthorizationSorter $authorizationSorter,
    ): Response {
        $this->denyAccessUnlessGranted('admin:manage:users');

        $authorizations = $authorizationRepository->findBy([
            'holder' => $user,
        ]);
        $authorizationSorter->sort($authorizations);

        return $this->render('users/authorizations/index.html.twig', [
            'user' => $user,
            'authorizations' => $authorizations,
        ]);
    }
}
