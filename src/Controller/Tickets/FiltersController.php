<?php

// This file is part of Bileto.
// Copyright 2022-2023 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\SearchEngine\Query;
use App\SearchEngine\TicketFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FiltersController extends BaseController
{
    #[Route('/tickets/filters/combine', name: 'combine filters', methods: ['POST'], priority: 1)]
    public function combine(Request $request): Response
    {
        /** @var ?string $textFilter */
        $textFilter = $request->request->get('text');

        /** @var mixed[] $filters */
        $filters = $request->request->all('filters');

        /** @var string $textualQuery */
        $textualQuery = $request->request->get('query', '');

        /** @var string $from */
        $from = $request->request->get('from', '');

        if (!$this->isPathRedirectable($from)) {
            throw $this->createNotFoundException('From parameter does not match any valid route.');
        }

        try {
            $query = Query::fromString($textualQuery);
        } catch (\Exception $e) {
            $query = null;
        }

        $ticketFilter = null;

        if ($query) {
            $ticketFilter = TicketFilter::fromQuery($query);
        }

        if (!$ticketFilter) {
            $ticketFilter = new TicketFilter();
        }

        if ($textFilter !== null) {
            $ticketFilter->setText($textFilter);
        }

        foreach ($filters as $filter => $values) {
            if (!$ticketFilter->isSupportedFilter($filter) || !is_array($values)) {
                continue;
            }

            /** @var value-of<TicketFilter::SUPPORTED_FILTERS> */
            $filter = $filter;

            $sanitizedValues = [];
            foreach ($values as $value) {
                if ($filter === 'assignee' && $value === 'no') {
                    $sanitizedValues[] = null;
                } elseif (is_string($value) && !empty($value)) {
                    $sanitizedValues[] = $value;
                }
            }

            try {
                $ticketFilter->setFilter($filter, $sanitizedValues);
            } catch (\UnexpectedValueException $e) {
                // does nothing on purpose
            }
        }

        $textualQuery = $ticketFilter->toTextualQuery();

        $from .= '?q=' . urlencode($textualQuery);

        return $this->redirect($from);
    }
}
