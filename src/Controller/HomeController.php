<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends BaseController
{
    #[Route('/', name: 'home', methods: ['GET', 'HEAD'])]
    public function show(): Response
    {
        return $this->redirectToRoute('organizations');
    }
}
