<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SettingsController extends BaseController
{
    #[Route('/settings', name: 'settings', methods: ['GET', 'HEAD'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('admin:see');

        return $this->render('settings/index.html.twig');
    }
}
