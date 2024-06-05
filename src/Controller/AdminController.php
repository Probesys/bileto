<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use App\Security\Authorizer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends BaseController
{
    #[Route('/admin', name: 'admin', methods: ['GET', 'HEAD'])]
    public function index(Authorizer $authorizer): Response
    {
        $this->denyAccessUnlessGranted('admin:see');

        $permissionsToRoutes = [
            'admin:manage:roles' => 'roles',
            'admin:manage:agents' => 'teams',
            'admin:manage:users' => 'users',
            'admin:manage:mailboxes' => 'mailboxes',
        ];

        foreach ($permissionsToRoutes as $permission => $route) {
            if ($authorizer->isGranted($permission)) {
                return $this->redirectToRoute($route);
            }
        }

        // This should probably never happen: an admin role should always have
        // one of the previous admin permissions.
        // However, theorically, an admin role could be created without any
        // permission. The template will handle this case.
        return $this->render('admin/index.html.twig');
    }
}
