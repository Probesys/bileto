<?php

// This file is part of Bileto.
// Copyright 2022 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Organizations;

use App\Controller\BaseController;
use App\Entity\Organization;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TicketsController extends BaseController
{
    #[Route('/organizations/{uid}/tickets', name: 'organization tickets', methods: ['GET', 'HEAD'])]
    public function index(Organization $organization): Response
    {
        return $this->render('organizations/tickets/index.html.twig', [
            'organization' => $organization,
        ]);
    }
}
