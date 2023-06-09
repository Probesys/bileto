<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends BaseController
{
    #[Route('/settings', name: 'settings', methods: ['GET', 'HEAD'])]
    public function index(Security $security): Response
    {
        $this->denyAccessUnlessGranted('admin:see');

        $permissionsToRoutes = [
            'admin:manage:organizations' => 'organizations',
            'admin:manage:roles' => 'roles',
            'admin:manage:users' => 'users',
            'admin:manage:mailboxes' => 'mailboxes',
        ];

        foreach ($permissionsToRoutes as $permission => $route) {
            if ($security->isGranted($permission)) {
                return $this->redirectToRoute($route);
            }
        }

        // This should probably never happen: an admin role should always have
        // one of the previous admin permissions.
        // However, theorically, an admin role could be created without any
        // permission. The template will handle this case.
        return $this->render('settings/index.html.twig');
    }
}
