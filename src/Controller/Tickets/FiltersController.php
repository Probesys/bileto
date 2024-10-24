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

class FiltersController extends BaseController
{
    #[Route('/tickets/filters/combine', name: 'combine filters', methods: ['POST'], priority: 1)]
    public function combine(Request $request): Response
    {
        $quickSearchForm = $this->createNamedForm('quick_search', Form\Search\QuickSearchForm::class);
        $quickSearchForm->handleRequest($request);

        $from = $quickSearchForm->get('from')->getData();

        if (!$this->isPathRedirectable($from)) {
            throw $this->createNotFoundException('From parameter does not match any valid route.');
        }

        if (!$quickSearchForm->isSubmitted() || !$quickSearchForm->isValid()) {
            dump($quickSearchForm);
            return $this->redirect($from);
        }

        $ticketFilter = $quickSearchForm->getData();

        $textualQuery = $ticketFilter->toTextualQuery();

        $from .= '?q=' . urlencode($textualQuery);

        return $this->redirect($from);
    }
}
