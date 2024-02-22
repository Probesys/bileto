<?php

// This file is part of Bileto.
// Copyright 2022-2024 Probesys
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Controller\Tickets;

use App\Controller\BaseController;
use App\Entity\Ticket;
use App\SearchEngine\Query;
use App\SearchEngine\TicketFilter;
use App\Service\ActorsLister;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FiltersController extends BaseController
{
    #[Route('/tickets/filters/{filter}/edit', name: 'edit filter', methods: ['GET', 'HEAD'], priority: 1)]
    public function edit(
        string $filter,
        Request $request,
        ActorsLister $actorsLister,
    ): Response {
        /** @var string $textualQuery */
        $textualQuery = $request->query->get('query', '');

        /** @var string $from */
        $from = $request->query->get('from', '');

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

        if ($filter === 'status') {
            return $this->render("tickets/filters/edit_status.html.twig", [
                'ticketFilter' => $ticketFilter,
                'openStatuses' => Ticket::getStatusesWithLabels('open'),
                'finishedStatuses' => Ticket::getStatusesWithLabels('finished'),
                'query' => $textualQuery,
                'from' => $from,
            ]);
        } elseif ($filter === 'type') {
            return $this->render("tickets/filters/edit_type.html.twig", [
                'ticketFilter' => $ticketFilter,
                'query' => $textualQuery,
                'from' => $from,
            ]);
        } elseif ($filter === 'priority' || $filter === 'urgency' || $filter === 'impact') {
            return $this->render("tickets/filters/edit_priority.html.twig", [
                'ticketFilter' => $ticketFilter,
                'query' => $textualQuery,
                'from' => $from,
            ]);
        } elseif ($filter === 'actors' || $filter === 'assignee' || $filter === 'requester' || $filter === 'involves') {
            $allUsers = $actorsLister->findAll();
            $agents = $actorsLister->findAll(roleType: 'agent');
            return $this->render("tickets/filters/edit_actors.html.twig", [
                'ticketFilter' => $ticketFilter,
                'allUsers' => $allUsers,
                'agents' => $agents,
                'query' => $textualQuery,
                'from' => $from,
            ]);
        } else {
            throw $this->createNotFoundException('Filter parameter is not supported.');
        }
    }

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
