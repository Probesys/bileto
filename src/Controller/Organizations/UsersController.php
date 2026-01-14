<?php

// This file is part of Bileto.
// Copyright 2022-2026 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Organization;
use App\Service\ActorsLister;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UsersController extends BaseController
{
    #[Route('/organizations/{uid:organization}/users', name: 'organization users', methods: ['GET', 'HEAD'])]
    public function index(
        Organization $organization,
        ActorsLister $actorsLister,
    ): Response {
        $this->denyAccessUnlessGranted('orga:see:users', $organization);

        // Don't list agents as normal users may have access to the list of
        // users. That would leak the emails of agents to users, and that's
        // probably not what we want.
        $users = $actorsLister->findByOrganization($organization, roleType: 'user');

        return $this->render('organizations/users/index.html.twig', [
            'organization' => $organization,
            'users' => $users,
        ]);
    }
}
