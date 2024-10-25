<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuickSearchesController extends BaseController
{
    #[Route('/tickets/searches', name: 'search tickets', methods: ['POST'], priority: 1)]
    public function new(Request $request): Response
    {
        $quickSearchForm = $this->createNamedForm('search', Form\Ticket\QuickSearchForm::class);
        $quickSearchForm->handleRequest($request);

        $from = $quickSearchForm->get('from')->getData();

        if (!$this->isPathRedirectable($from)) {
            throw $this->createNotFoundException('From parameter does not match any valid route.');
        }

        if (!$quickSearchForm->isSubmitted() || !$quickSearchForm->isValid()) {
            return $this->redirect($from);
        }

        $ticketFilter = $quickSearchForm->getData();

        $from .= '?q=' . urlencode($ticketFilter->toTextualQuery());

        return $this->redirect($from);
    }
}
